<?php

namespace Tests\Unit;

use App\Support\StrHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrHelperTest extends TestCase
{

    public function test_limit_returns_original_when_shorter_than_limit(): void
    {
        $this->assertEquals('short', StrHelper::limit('short', 10));
    }

    public function test_limit_truncates_and_appends_suffix(): void
    {
        $value = 'this-is-a-very-long-string';
        $result = StrHelper::limit($value, 10, '...');

        $this->assertEquals('this-is...', $result);
    }

    public function test_limit_handles_suffix_longer_than_limit(): void
    {
        $value = 'abcdef';
        $result = StrHelper::limit($value, 3, '--------');

        $this->assertEquals('abc', $result);
    }
}
