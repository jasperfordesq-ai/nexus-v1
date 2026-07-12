<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventRegistrationDataClassification;
use App\Enums\EventRegistrationFormStatus;
use App\Enums\EventRegistrationQuestionType;
use App\Exceptions\EventRegistrationFoundationException;
use App\Models\EventRegistrationFormVersion;
use App\Models\User;
use App\Support\Events\EventRegistrationFoundationSupport;
use App\Support\Events\EventRegistrationFormRuleSet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/** Immutable-on-publish, copy-on-write registration form definitions. */
final class EventRegistrationFormService
{
    private const MAX_QUESTIONS = 100;
    private const FORM_FIELDS = ['name', 'description', 'questions'];
    private const QUESTION_FIELDS = [
        'key',
        'stable_key',
        'type',
        'question_type',
        'prompt',
        'help_text',
        'required',
        'is_required',
        'data_classification',
        'classification',
        'purpose',
        'retention_days',
        'choices',
        'choice_options',
        'validation_rules',
        'visibility_rules',
        'displayed_text',
        'displayed_text_version',
    ];

    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
        private readonly EventRegistrationFormRuleSet $rules = new EventRegistrationFormRuleSet(),
    ) {
    }

    /**
     * @param list<array<string,mixed>> $questions
     * @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int}
     */
    public function createDraft(
        int $eventId,
        User|int $actor,
        string $name,
        ?string $description,
        array $questions,
        int $expectedSettingsRevision,
        string $idempotencyKey,
    ): array {
        return $this->writeDraft(
            $eventId,
            null,
            $actor,
            ['name' => $name, 'description' => $description, 'questions' => $questions],
            null,
            $expectedSettingsRevision,
            $idempotencyKey,
            'form_draft_created',
        );
    }

    /**
     * @param array{name?:mixed,description?:mixed,questions?:mixed} $attributes
     * @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int}
     */
    public function updateDraft(
        int $eventId,
        int $formId,
        User|int $actor,
        array $attributes,
        int $expectedFormRevision,
        int $expectedSettingsRevision,
        string $idempotencyKey,
    ): array {
        $unknown = array_diff(array_keys($attributes), self::FORM_FIELDS);
        if ($unknown !== []) {
            throw new EventRegistrationFoundationException('event_registration_form_fields_unknown');
        }

        return $this->writeDraft(
            $eventId,
            $formId,
            $actor,
            $attributes,
            $expectedFormRevision,
            $expectedSettingsRevision,
            $idempotencyKey,
            'form_draft_updated',
        );
    }

    /** @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int} */
    public function forkPublished(
        int $eventId,
        int $publishedFormId,
        User|int $actor,
        int $expectedSettingsRevision,
        string $idempotencyKey,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $source = EventRegistrationFormVersion::withoutGlobalScopes()
            ->with('questions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereKey($publishedFormId)
            ->first();
        if ($source === null || $source->status !== EventRegistrationFormStatus::Published) {
            throw new EventRegistrationFoundationException('event_registration_published_form_not_found');
        }
        $questions = $source->questions->map(static fn ($question): array => [
            'stable_key' => (string) $question->stable_key,
            'question_type' => $question->question_type->value,
            'prompt' => (string) $question->prompt,
            'help_text' => $question->help_text,
            'is_required' => (bool) $question->is_required,
            'data_classification' => $question->data_classification->value,
            'purpose' => (string) $question->purpose,
            'retention_days' => (int) $question->retention_days,
            'choice_options' => $question->choice_options,
            'validation_rules' => $question->validation_rules,
            'visibility_rules' => $question->visibility_rules,
            'displayed_text' => $question->displayed_text,
            'displayed_text_version' => $question->displayed_text_version,
        ])->all();

        return $this->writeDraft(
            $eventId,
            null,
            $actor,
            [
                'name' => (string) $source->name,
                'description' => $source->description,
                'questions' => $questions,
            ],
            null,
            $expectedSettingsRevision,
            $idempotencyKey,
            'form_version_forked',
        );
    }

    /** @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int} */
    public function publish(
        int $eventId,
        int $formId,
        User|int $actor,
        int $expectedFormRevision,
        int $expectedSettingsRevision,
        string $idempotencyKey,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $keyHash = $this->support->idempotencyHash($idempotencyKey);

        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $formId,
            $actor,
            $expectedFormRevision,
            $expectedSettingsRevision,
            $keyHash,
        ): array {
            $event = $this->support->concreteEvent($tenantId, $eventId, true);
            $persistedActor = $this->support->actor($tenantId, $actor, true);
            $this->support->authorizeManager($persistedActor, $event);
            $settings = $this->settings($tenantId, $eventId, true);
            $form = $this->form($tenantId, $eventId, $formId, true);
            $requestHash = $this->support->requestHash([
                'action' => 'form_published',
                'event_id' => $eventId,
                'form_id' => $formId,
                'actor_id' => (int) $persistedActor->id,
                'expected_form_revision' => $expectedFormRevision,
                'expected_settings_revision' => $expectedSettingsRevision,
            ]);
            $replay = $this->historyReplay($tenantId, $keyHash, $requestHash);
            if ($replay !== null) {
                return $this->replayResult($tenantId, $eventId, $replay);
            }
            if ((string) $form->status !== EventRegistrationFormStatus::Draft->value) {
                throw new EventRegistrationFoundationException('event_registration_published_form_immutable');
            }
            if ((int) $form->revision !== $expectedFormRevision
                || (int) $settings->revision !== $expectedSettingsRevision) {
                throw new EventRegistrationFoundationException('event_registration_form_revision_conflict');
            }
            $questions = DB::table('event_registration_form_questions')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('form_version_id', $formId)
                ->orderBy('position')
                ->get();
            if ($questions->isEmpty()) {
                throw new EventRegistrationFoundationException('event_registration_form_questions_required');
            }
            $definitionHash = $this->support->requestHash([
                'version_number' => (int) $form->version_number,
                'name' => (string) $form->name,
                'description' => $form->description,
                'questions' => $questions->map(static fn (object $question): array => (array) $question)->all(),
            ]);
            $now = CarbonImmutable::now('UTC');
            $updated = DB::table('event_registration_form_versions')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', $formId)
                ->where('revision', $expectedFormRevision)
                ->where('status', EventRegistrationFormStatus::Draft->value)
                ->update([
                    'revision' => $expectedFormRevision + 1,
                    'status' => EventRegistrationFormStatus::Published->value,
                    'definition_hash' => $definitionHash,
                    'updated_by' => (int) $persistedActor->id,
                    'published_by' => (int) $persistedActor->id,
                    'published_at' => $now,
                    'updated_at' => $now,
                ]);
            if ($updated !== 1) {
                throw new EventRegistrationFoundationException('event_registration_form_revision_conflict');
            }
            $settingsRevision = $expectedSettingsRevision + 1;
            if (DB::table('event_registration_settings')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('revision', $expectedSettingsRevision)
                ->update([
                    'revision' => $settingsRevision,
                    'form_state' => 'published',
                    'published_form_version' => (int) $form->version_number,
                    'updated_by' => (int) $persistedActor->id,
                    'updated_at' => $now,
                ]) !== 1) {
                throw new EventRegistrationFoundationException('event_registration_settings_revision_conflict');
            }
            $this->recordSettingsHistory(
                $tenantId,
                $eventId,
                (int) $settings->id,
                $settingsRevision,
                'form_published',
                (int) $persistedActor->id,
                $keyHash,
                $requestHash,
                $formId,
                $now,
            );

            return [
                'form' => $this->formModel($tenantId, $eventId, $formId),
                'changed' => true,
                'settings_revision' => $settingsRevision,
            ];
        }, 3);
    }

    /**
     * @param array<string,mixed> $attributes
     * @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int}
     */
    private function writeDraft(
        int $eventId,
        ?int $formId,
        User|int $actor,
        array $attributes,
        ?int $expectedFormRevision,
        int $expectedSettingsRevision,
        string $idempotencyKey,
        string $action,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $keyHash = $this->support->idempotencyHash($idempotencyKey);

        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $formId,
            $actor,
            $attributes,
            $expectedFormRevision,
            $expectedSettingsRevision,
            $keyHash,
            $action,
        ): array {
            $event = $this->support->concreteEvent($tenantId, $eventId, true);
            $persistedActor = $this->support->actor($tenantId, $actor, true);
            $this->support->authorizeManager($persistedActor, $event);
            $settings = $this->settings($tenantId, $eventId, true);
            $current = $formId === null ? null : $this->form($tenantId, $eventId, $formId, true);
            $normalized = $this->normalizeForm($attributes, $current, $tenantId, $eventId, $formId);
            $requestHash = $this->support->requestHash([
                'action' => $action,
                'event_id' => $eventId,
                'form_id' => $formId,
                'actor_id' => (int) $persistedActor->id,
                'expected_form_revision' => $expectedFormRevision,
                'expected_settings_revision' => $expectedSettingsRevision,
                'definition' => $normalized,
            ]);
            $replay = $this->historyReplay($tenantId, $keyHash, $requestHash);
            if ($replay !== null) {
                return $this->replayResult($tenantId, $eventId, $replay);
            }
            if ((int) $settings->revision !== $expectedSettingsRevision) {
                throw new EventRegistrationFoundationException('event_registration_settings_revision_conflict');
            }
            if ($current !== null
                && (string) $current->status !== EventRegistrationFormStatus::Draft->value) {
                throw new EventRegistrationFoundationException('event_registration_published_form_immutable');
            }
            if ($current !== null && $expectedFormRevision !== (int) $current->revision) {
                throw new EventRegistrationFoundationException('event_registration_form_revision_conflict');
            }

            $now = CarbonImmutable::now('UTC');
            if ($current === null) {
                $versionNumber = ((int) DB::table('event_registration_form_versions')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->max('version_number')) + 1;
                $formRevision = 1;
                $writtenFormId = (int) DB::table('event_registration_form_versions')->insertGetId([
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'version_number' => $versionNumber,
                    'revision' => $formRevision,
                    'status' => EventRegistrationFormStatus::Draft->value,
                    'name' => $normalized['name'],
                    'description' => $normalized['description'],
                    'definition_hash' => null,
                    'created_by' => (int) $persistedActor->id,
                    'updated_by' => (int) $persistedActor->id,
                    'published_by' => null,
                    'published_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $writtenFormId = (int) $current->id;
                $formRevision = (int) $current->revision + 1;
                if (DB::table('event_registration_form_versions')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('id', $writtenFormId)
                    ->where('revision', $expectedFormRevision)
                    ->where('status', EventRegistrationFormStatus::Draft->value)
                    ->update([
                        'revision' => $formRevision,
                        'name' => $normalized['name'],
                        'description' => $normalized['description'],
                        'updated_by' => (int) $persistedActor->id,
                        'updated_at' => $now,
                    ]) !== 1) {
                    throw new EventRegistrationFoundationException('event_registration_form_revision_conflict');
                }
                DB::table('event_registration_form_questions')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('form_version_id', $writtenFormId)
                    ->delete();
            }
            $this->insertQuestions($tenantId, $eventId, $writtenFormId, $normalized['questions'], $now);
            $settingsRevision = $expectedSettingsRevision + 1;
            $hasPublishedForm = $settings->published_form_version !== null;
            if (DB::table('event_registration_settings')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('revision', $expectedSettingsRevision)
                ->update([
                    'revision' => $settingsRevision,
                    'form_state' => $hasPublishedForm ? 'published' : 'draft',
                    'published_form_version' => $settings->published_form_version,
                    'updated_by' => (int) $persistedActor->id,
                    'updated_at' => $now,
                ]) !== 1) {
                throw new EventRegistrationFoundationException('event_registration_settings_revision_conflict');
            }
            $this->recordSettingsHistory(
                $tenantId,
                $eventId,
                (int) $settings->id,
                $settingsRevision,
                $action,
                (int) $persistedActor->id,
                $keyHash,
                $requestHash,
                $writtenFormId,
                $now,
            );

            return [
                'form' => $this->formModel($tenantId, $eventId, $writtenFormId),
                'changed' => true,
                'settings_revision' => $settingsRevision,
            ];
        }, 3);
    }

    /**
     * @param array<string,mixed> $attributes
     * @return array{name:string,description:?string,questions:list<array<string,mixed>>}
     */
    private function normalizeForm(
        array $attributes,
        ?stdClass $current,
        int $tenantId,
        int $eventId,
        ?int $formId,
    ): array {
        $name = trim((string) ($attributes['name'] ?? $current?->name ?? ''));
        if ($name === '' || mb_strlen($name) > 191) {
            throw new EventRegistrationFoundationException('event_registration_form_name_invalid');
        }
        $descriptionValue = $attributes['description'] ?? $current?->description;
        $description = $descriptionValue === null ? null : trim((string) $descriptionValue);
        if ($description !== null && mb_strlen($description) > 4000) {
            throw new EventRegistrationFoundationException('event_registration_form_description_invalid');
        }
        $questionInput = $attributes['questions'] ?? null;
        if ($questionInput === null && $formId !== null) {
            $questionInput = DB::table('event_registration_form_questions')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('form_version_id', $formId)
                ->orderBy('position')
                ->get()
                ->map(static fn (object $row): array => [
                    'stable_key' => $row->stable_key,
                    'question_type' => $row->question_type,
                    'prompt' => $row->prompt,
                    'help_text' => $row->help_text,
                    'is_required' => (bool) $row->is_required,
                    'data_classification' => $row->data_classification,
                    'purpose' => $row->purpose,
                    'retention_days' => (int) $row->retention_days,
                    'choice_options' => $row->choice_options === null
                        ? null
                        : json_decode((string) $row->choice_options, true, 512, JSON_THROW_ON_ERROR),
                    'validation_rules' => $row->validation_rules === null
                        ? null
                        : json_decode((string) $row->validation_rules, true, 512, JSON_THROW_ON_ERROR),
                    'visibility_rules' => $row->visibility_rules === null
                        ? null
                        : json_decode((string) $row->visibility_rules, true, 512, JSON_THROW_ON_ERROR),
                    'displayed_text' => $row->displayed_text,
                    'displayed_text_version' => $row->displayed_text_version,
                ])->all();
        }
        if (! is_array($questionInput)
            || ! array_is_list($questionInput)
            || $questionInput === []
            || count($questionInput) > self::MAX_QUESTIONS) {
            throw new EventRegistrationFoundationException('event_registration_form_questions_invalid');
        }
        $questions = [];
        $keys = [];
        $earlierQuestions = [];
        foreach ($questionInput as $position => $question) {
            if (! is_array($question)) {
                throw new EventRegistrationFoundationException('event_registration_question_invalid');
            }
            $normalized = $this->normalizeQuestion($question, $position + 1, $earlierQuestions);
            if (isset($keys[$normalized['stable_key']])) {
                throw new EventRegistrationFoundationException('event_registration_question_key_duplicate');
            }
            $keys[$normalized['stable_key']] = true;
            $earlierQuestions[$normalized['stable_key']] = EventRegistrationQuestionType::from(
                $normalized['question_type'],
            );
            $questions[] = $normalized;
        }

        return ['name' => $name, 'description' => $description, 'questions' => $questions];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,EventRegistrationQuestionType> $earlierQuestions
     * @return array<string,mixed>
     */
    private function normalizeQuestion(array $input, int $position, array $earlierQuestions): array
    {
        if (array_diff(array_keys($input), self::QUESTION_FIELDS) !== []) {
            throw new EventRegistrationFoundationException('event_registration_question_fields_unknown');
        }
        $this->assertAliases($input, [['key', 'stable_key'], ['type', 'question_type'], ['required', 'is_required'], ['data_classification', 'classification'], ['choices', 'choice_options']]);
        $key = trim((string) ($input['stable_key'] ?? $input['key'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
            throw new EventRegistrationFoundationException('event_registration_question_key_invalid');
        }
        $type = EventRegistrationQuestionType::tryFrom(
            trim((string) ($input['question_type'] ?? $input['type'] ?? '')),
        );
        if ($type === null) {
            throw new EventRegistrationFoundationException('event_registration_question_type_invalid');
        }
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $purpose = trim((string) ($input['purpose'] ?? ''));
        if ($prompt === '' || mb_strlen($prompt) > 2000 || $purpose === '' || mb_strlen($purpose) > 500) {
            throw new EventRegistrationFoundationException('event_registration_question_text_invalid');
        }
        $classification = EventRegistrationDataClassification::tryFrom(
            trim((string) ($input['data_classification'] ?? $input['classification'] ?? '')),
        );
        if ($classification === null) {
            throw new EventRegistrationFoundationException('event_registration_question_classification_invalid');
        }
        if (in_array($type, [EventRegistrationQuestionType::Dietary, EventRegistrationQuestionType::Accessibility], true)
            && ! in_array($classification, [EventRegistrationDataClassification::Confidential, EventRegistrationDataClassification::Sensitive], true)) {
            throw new EventRegistrationFoundationException('event_registration_sensitive_question_classification_required');
        }
        $retention = filter_var($input['retention_days'] ?? null, FILTER_VALIDATE_INT);
        if ($retention === false || $retention < 1 || $retention > 36500) {
            throw new EventRegistrationFoundationException('event_registration_question_retention_invalid');
        }
        $choices = $input['choice_options'] ?? $input['choices'] ?? null;
        $normalizedChoices = null;
        if ($type->isChoice()) {
            if (! is_array($choices) || ! array_is_list($choices) || count($choices) < 2 || count($choices) > 100) {
                throw new EventRegistrationFoundationException('event_registration_question_choices_invalid');
            }
            $normalizedChoices = [];
            foreach ($choices as $choice) {
                if (! is_string($choice) || trim($choice) === '' || mb_strlen(trim($choice)) > 191) {
                    throw new EventRegistrationFoundationException('event_registration_question_choice_invalid');
                }
                $normalizedChoices[] = trim($choice);
            }
            if (count(array_unique($normalizedChoices)) !== count($normalizedChoices)) {
                throw new EventRegistrationFoundationException('event_registration_question_choices_duplicate');
            }
        } elseif ($choices !== null && $choices !== []) {
            throw new EventRegistrationFoundationException('event_registration_question_choices_unexpected');
        }
        $displayedText = $input['displayed_text'] ?? null;
        $displayedVersion = $input['displayed_text_version'] ?? null;
        if ($type->isConsent()) {
            $displayedText = trim((string) $displayedText);
            $displayedVersion = trim((string) $displayedVersion);
            if ($displayedText === '' || mb_strlen($displayedText) > 20000
                || $displayedVersion === '' || mb_strlen($displayedVersion) > 64) {
                throw new EventRegistrationFoundationException('event_registration_consent_evidence_invalid');
            }
        } elseif ($displayedText !== null || $displayedVersion !== null) {
            throw new EventRegistrationFoundationException('event_registration_consent_evidence_unexpected');
        }
        $requiredValue = $input['is_required'] ?? $input['required'] ?? false;
        $required = filter_var($requiredValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($required === null) {
            throw new EventRegistrationFoundationException('event_registration_question_required_invalid');
        }

        $helpText = isset($input['help_text']) ? trim((string) $input['help_text']) : null;
        if ($helpText !== null && mb_strlen($helpText) > 4000) {
            throw new EventRegistrationFoundationException('event_registration_question_help_text_invalid');
        }

        $validationRules = $this->rules->normalizeValidation(
            $type,
            $input['validation_rules'] ?? null,
        );
        $visibilityRules = $this->rules->normalizeVisibility(
            $input['visibility_rules'] ?? null,
            $earlierQuestions,
        );

        return [
            'stable_key' => $key,
            'position' => $position,
            'question_type' => $type->value,
            'prompt' => $prompt,
            'help_text' => $helpText,
            'is_required' => $required,
            'data_classification' => $classification->value,
            'purpose' => $purpose,
            'retention_days' => (int) $retention,
            'choice_options' => $normalizedChoices,
            'validation_rules' => $validationRules,
            'visibility_rules' => $visibilityRules,
            'displayed_text' => $type->isConsent() ? $displayedText : null,
            'displayed_text_version' => $type->isConsent() ? $displayedVersion : null,
        ];
    }

    /** @param list<array<string,mixed>> $questions */
    private function insertQuestions(
        int $tenantId,
        int $eventId,
        int $formId,
        array $questions,
        CarbonImmutable $now,
    ): void {
        DB::table('event_registration_form_questions')->insert(array_map(
            static fn (array $question): array => [
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'form_version_id' => $formId,
                ...$question,
                'choice_options' => $question['choice_options'] === null
                    ? null
                    : json_encode($question['choice_options'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'validation_rules' => $question['validation_rules'] === null
                    ? null
                    : json_encode($question['validation_rules'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'visibility_rules' => $question['visibility_rules'] === null
                    ? null
                    : json_encode($question['visibility_rules'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $questions,
        ));
    }

    private function recordSettingsHistory(
        int $tenantId,
        int $eventId,
        int $settingsId,
        int $revision,
        string $action,
        int $actorId,
        string $keyHash,
        string $requestHash,
        int $formId,
        CarbonImmutable $now,
    ): void {
        DB::table('event_registration_settings_history')->insert([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'settings_id' => $settingsId,
            'revision' => $revision,
            'action' => $action,
            'actor_user_id' => $actorId,
            'idempotency_hash' => $keyHash,
            'request_hash' => $requestHash,
            'changed_fields' => json_encode([
                'form_state',
                'published_form_version',
                'form_version_id' => $formId,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    private function settings(int $tenantId, int $eventId, bool $lock): stdClass
    {
        $query = DB::table('event_registration_settings')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if ($row === null) {
            throw new EventRegistrationFoundationException('event_registration_settings_not_found');
        }

        return $row;
    }

    private function form(int $tenantId, int $eventId, int $formId, bool $lock): stdClass
    {
        $query = DB::table('event_registration_form_versions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $formId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if ($row === null) {
            throw new EventRegistrationFoundationException('event_registration_form_not_found');
        }

        return $row;
    }

    private function historyReplay(int $tenantId, string $keyHash, string $requestHash): ?stdClass
    {
        $row = DB::table('event_registration_settings_history')
            ->where('tenant_id', $tenantId)
            ->where('idempotency_hash', $keyHash)
            ->first();
        if ($row !== null && ! hash_equals((string) $row->request_hash, $requestHash)) {
            throw new EventRegistrationFoundationException('event_registration_form_idempotency_conflict');
        }

        return $row;
    }

    /** @return array{form:EventRegistrationFormVersion,changed:bool,settings_revision:int} */
    private function replayResult(int $tenantId, int $eventId, stdClass $history): array
    {
        $changed = json_decode((string) $history->changed_fields, true, 512, JSON_THROW_ON_ERROR);
        $formId = is_array($changed) ? (int) ($changed['form_version_id'] ?? 0) : 0;
        if ($formId <= 0) {
            throw new EventRegistrationFoundationException('event_registration_form_replay_evidence_invalid');
        }

        return [
            'form' => $this->formModel($tenantId, $eventId, $formId),
            'changed' => false,
            'settings_revision' => (int) $history->revision,
        ];
    }

    private function formModel(int $tenantId, int $eventId, int $formId): EventRegistrationFormVersion
    {
        return EventRegistrationFormVersion::withoutGlobalScopes()
            ->with('questions')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereKey($formId)
            ->firstOrFail();
    }

    /** @param array<string,mixed> $input @param list<array{0:string,1:string}> $pairs */
    private function assertAliases(array $input, array $pairs): void
    {
        foreach ($pairs as [$left, $right]) {
            if (array_key_exists($left, $input) && array_key_exists($right, $input)) {
                throw new EventRegistrationFoundationException('event_registration_question_alias_conflict');
            }
        }
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_registration_settings',
            'event_registration_settings_history',
            'event_registration_form_versions',
            'event_registration_form_questions',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventRegistrationFoundationException('event_registration_form_schema_unavailable');
            }
        }
        if (! Schema::hasColumns('event_registration_form_questions', [
            'validation_rules',
            'visibility_rules',
        ])) {
            throw new EventRegistrationFoundationException('event_registration_form_schema_unavailable');
        }
    }
}
