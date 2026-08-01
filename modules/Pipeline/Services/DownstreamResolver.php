<?php

declare(strict_types=1);

namespace Modules\Pipeline\Services;

/**
 * "Rerun from here" needs the set of steps whose results are no longer trustworthy:
 * the step itself plus everything that consumes it, directly or indirectly.
 */
final class DownstreamResolver
{
    /**
     * @param  array<string, list<string>>  $dependsOnByKey
     * @return list<string>
     */
    public function closureFor(array $dependsOnByKey, string $stepKey): array
    {
        $closure = [$stepKey => true];

        do {
            $changed = false;

            foreach ($dependsOnByKey as $key => $dependencies) {
                if (isset($closure[$key])) {
                    continue;
                }

                foreach ($dependencies as $dependency) {
                    if (isset($closure[$dependency])) {
                        $closure[$key] = true;
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed);

        $keys = array_keys($closure);
        sort($keys);

        return $keys;
    }
}
