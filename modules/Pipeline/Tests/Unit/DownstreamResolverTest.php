<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use Modules\Pipeline\Services\DownstreamResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DownstreamResolverTest extends TestCase
{
    private DownstreamResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DownstreamResolver;
    }

    #[Test]
    public function a_leaf_step_closes_over_only_itself(): void
    {
        $graph = ['a' => [], 'b' => ['a'], 'c' => ['b']];

        $this->assertSame(['c'], $this->resolver->closureFor($graph, 'c'));
    }

    #[Test]
    public function it_walks_the_whole_transitive_chain(): void
    {
        $graph = ['a' => [], 'b' => ['a'], 'c' => ['b']];

        $this->assertSame(['a', 'b', 'c'], $this->resolver->closureFor($graph, 'a'));
    }

    #[Test]
    public function it_collects_every_branch_of_a_fan_out(): void
    {
        $graph = ['a' => [], 'b' => ['a'], 'c' => ['a'], 'd' => ['b', 'c'], 'e' => []];

        $this->assertSame(['a', 'b', 'c', 'd'], $this->resolver->closureFor($graph, 'a'));
    }

    #[Test]
    public function an_unrelated_branch_is_left_alone(): void
    {
        $graph = ['a' => [], 'b' => ['a'], 'x' => [], 'y' => ['x']];

        $this->assertSame(['x', 'y'], $this->resolver->closureFor($graph, 'x'));
    }
}
