<?php

namespace Tests\Unit;

use App\Support\ProposedAction;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProposedActionTest extends TestCase
{
    public function test_it_accepts_string_and_int_context_values(): void
    {
        $action = new ProposedAction(
            type: 'test_action',
            summary: 'Test',
            context: ['Amount' => '12.99 dollars', 'Days' => 30],
        );

        $this->assertSame('12.99 dollars', $action->context['Amount']);
        $this->assertSame(30, $action->context['Days']);
    }

    public function test_it_rejects_a_non_scalar_context_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Context value for "Details" must be a string or an int, got array.');

        new ProposedAction(
            type: 'test_action',
            summary: 'Test',
            context: ['Details' => ['nested' => 'array']],
        );
    }

    public function test_it_rejects_a_float_context_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Context value for "Amount" must be a string or an int, got float.');

        new ProposedAction(
            type: 'test_action',
            summary: 'Test',
            context: ['Amount' => 12.99],
        );
    }
}
