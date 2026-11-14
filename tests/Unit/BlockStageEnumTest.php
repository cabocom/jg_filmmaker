<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BlockStage;
use App\Models\ScriptBlock;
use PHPUnit\Framework\TestCase;

class BlockStageEnumTest extends TestCase
{
    public function test_block_stage_enum_values_and_labels(): void
    {
        $this->assertSame('raw', BlockStage::RAW->value);
        $this->assertSame('structured', BlockStage::STRUCTURED->value);
        $this->assertSame('screenplay', BlockStage::SCREENPLAY->value);
        $this->assertSame('shots', BlockStage::SHOTS->value);

        $this->assertSame('Bruto', BlockStage::RAW->label());
        $this->assertSame('Desglose de Planos', BlockStage::SHOTS->label());

        $this->assertTrue(BlockStage::SCREENPLAY->isAdvancedThan(BlockStage::RAW));
        $this->assertFalse(BlockStage::RAW->isAdvancedThan(BlockStage::STRUCTURED));
    }

    public function test_script_block_resolves_stage_enum(): void
    {
        $block = new ScriptBlock(['stage' => 'screenplay']);
        $this->assertSame(BlockStage::SCREENPLAY, $block->stageEnum());

        $rawBlock = new ScriptBlock(['stage' => 'unknown_stage']);
        $this->assertSame(BlockStage::RAW, $rawBlock->stageEnum());
    }
}
