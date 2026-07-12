<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Discussion;

/** @deprecated Use GroupDiscussionFactory. */
class DiscussionFactory extends GroupDiscussionFactory
{
    protected $model = Discussion::class;
}
