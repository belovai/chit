<?php

declare(strict_types=1);

namespace Modules\Pipeline\Enums;

enum ArtifactKind: string
{
    case Json = 'json';
    case Text = 'text';
    case Binary = 'binary';
}
