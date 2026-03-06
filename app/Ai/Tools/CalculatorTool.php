<?php

namespace App\Ai\Tools;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

class CalculatorTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Performs basic arithmetic operations (add, subtract, multiply, divide) on two numbers.';
    }

    public function handle(Request $request): Stringable|string
    {
        $a = $request['a'];
        $b = $request['b'];
        $operation = $request['operation'];

        return match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : 'Division by zero error',
            default => 'Unknown operation',
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'a' => $schema->number()->required(),
            'b' => $schema->number()->required(),
            'operation' => $schema->string()->enum(['add', 'subtract', 'multiply', 'divide'])->required(),
        ];
    }
}
