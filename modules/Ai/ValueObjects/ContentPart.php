<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

/**
 * A closed set: TextPart | ImagePart. One file each, because composer resolves
 * `Modules\` from `modules/` with PSR-4 — a class declared in a file of another
 * name is only ever found by accident, when something else loaded that file first.
 */
interface ContentPart {}
