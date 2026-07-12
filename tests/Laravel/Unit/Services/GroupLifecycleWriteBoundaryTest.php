<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Laravel\TestCase;

final class GroupLifecycleWriteBoundaryTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED_WRITERS = [
        'app/Services/GroupLifecycleService.php',
        'app/Services/GroupService.php',
    ];

    /** @var list<string> */
    private const ALLOWED_CREATORS = [
        'app/Services/GroupService.php',
    ];

    public function test_group_lifecycle_writes_are_confined_to_the_canonical_services(): void
    {
        $root = dirname(__DIR__, 4);
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $printer = new Standard();
        $violations = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (in_array($relative, self::ALLOWED_WRITERS, true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                self::fail('Unable to read ' . $relative);
            }
            $statements = $parser->parse($source) ?? [];

            $visitor = new class(
                $relative,
                $printer,
                in_array($relative, self::ALLOWED_CREATORS, true),
            ) extends NodeVisitorAbstract
            {
                /** @var list<string> */
                public array $violations = [];

                public function __construct(
                    private readonly string $file,
                    private readonly Standard $printer,
                    private readonly bool $mayCreateGroups,
                ) {}

                public function enterNode(Node $node): null
                {
                    if ($node instanceof Assign || $node instanceof AssignOp) {
                        if ($this->isLifecycleProperty($node->var)) {
                            $this->record($node, 'direct model lifecycle assignment');
                        }
                    }

                    if ($node instanceof MethodCall && $this->isLifecycleMutationCall($node)) {
                        $this->record($node, 'direct groups-table lifecycle mutation');
                    }

                    if (! $this->mayCreateGroups && $node instanceof MethodCall && $this->isGroupCreationCall($node)) {
                        $this->record($node, 'group creation outside canonical GroupService');
                    }

                    if ($node instanceof StaticCall && $this->isRawLifecycleUpdate($node)) {
                        $this->record($node, 'raw SQL groups lifecycle mutation');
                    }

                    return null;
                }

                private function isLifecycleProperty(Node $node): bool
                {
                    if (! $node instanceof PropertyFetch || ! $node->name instanceof Node\Identifier) {
                        return false;
                    }

                    return in_array($node->name->toString(), ['status', 'is_active'], true)
                        && $node->var instanceof Node\Expr\Variable
                        && $node->var->name === 'group';
                }

                private function isLifecycleMutationCall(MethodCall $call): bool
                {
                    if (! $call->name instanceof Node\Identifier) {
                        return false;
                    }

                    if (! in_array($call->name->toString(), ['insert', 'insertGetId', 'update', 'updateOrInsert'], true)) {
                        return false;
                    }

                    return $this->chainTargetsGroups($call->var)
                        && $this->argumentsContainLifecycleKey($call->args);
                }

                private function isGroupCreationCall(MethodCall $call): bool
                {
                    if (! $call->name instanceof Node\Identifier
                        || ! in_array($call->name->toString(), ['insert', 'insertGetId', 'updateOrInsert'], true)) {
                        return false;
                    }

                    return $this->chainTargetsGroups($call->var);
                }

                private function chainTargetsGroups(Node $node): bool
                {
                    if ($node instanceof MethodCall) {
                        return $this->chainTargetsGroups($node->var);
                    }

                    if (! $node instanceof StaticCall
                        || ! $node->class instanceof Name
                        || ! $node->name instanceof Node\Identifier) {
                        return false;
                    }

                    $class = $node->class->getLast();
                    if ($class === 'Group') {
                        return true;
                    }

                    return $class === 'DB'
                        && $node->name->toString() === 'table'
                        && isset($node->args[0])
                        && $node->args[0]->value instanceof String_
                        && $node->args[0]->value->value === 'groups';
                }

                /** @param list<Node\Arg> $arguments */
                private function argumentsContainLifecycleKey(array $arguments): bool
                {
                    foreach ($arguments as $argument) {
                        if ($this->nodeContainsLifecycleKey($argument->value)) {
                            return true;
                        }
                    }

                    return false;
                }

                private function nodeContainsLifecycleKey(Node $node): bool
                {
                    if ($node instanceof Array_) {
                        foreach ($node->items as $item) {
                            if ($item === null) {
                                continue;
                            }
                            if ($item->key instanceof String_
                                && in_array($item->key->value, ['status', 'is_active'], true)) {
                                return true;
                            }
                            if ($this->nodeContainsLifecycleKey($item->value)) {
                                return true;
                            }
                        }
                    }

                    return false;
                }

                private function isRawLifecycleUpdate(StaticCall $call): bool
                {
                    if (! $call->class instanceof Name
                        || $call->class->getLast() !== 'DB'
                        || ! $call->name instanceof Node\Identifier
                        || ! in_array($call->name->toString(), ['statement', 'update', 'unprepared'], true)
                        || ! isset($call->args[0])
                        || ! $call->args[0]->value instanceof String_) {
                        return false;
                    }

                    $sql = $call->args[0]->value->value;

                    return preg_match('/UPDATE\\s+`?groups`?\\s+SET[\\s\\S]*(?:status|is_active)\\s*=/i', $sql) === 1;
                }

                private function record(Node $node, string $kind): void
                {
                    $this->violations[] = sprintf(
                        '%s:%d %s: %s',
                        $this->file,
                        $node->getStartLine(),
                        $kind,
                        trim($this->printer->prettyPrintExpr($node)),
                    );
                }
            };

            $traverser = new \PhpParser\NodeTraverser($visitor);
            $traverser->traverse($statements);
            array_push($violations, ...$visitor->violations);
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }
}
