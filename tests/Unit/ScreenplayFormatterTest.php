<?php

namespace Tests\Unit;

use App\Services\ScreenplayFormatterService;
use PHPUnit\Framework\TestCase;

class ScreenplayFormatterTest extends TestCase
{
    public function test_formats_character_and_dialogue_as_story_architect_block(): void
    {
        $fountain = "Juan está de pie entre los troncos, observa a Pedro con una postura imponente.\n\nJUAN\nHa llegado el momento de dejar de huir de tus actos. El que la hace la paga.";

        $html = ScreenplayFormatterService::formatToHtml($fountain);

        $this->assertStringContainsString('screenplay-action', $html);
        $this->assertStringContainsString('Juan está de pie entre los troncos', $html);
        $this->assertStringContainsString('screenplay-dialogue-block', $html);
        $this->assertStringContainsString('screenplay-character', $html);
        $this->assertStringContainsString('JUAN', $html);
        $this->assertStringContainsString('screenplay-dialogue', $html);
        $this->assertStringContainsString('Ha llegado el momento de dejar de huir de tus actos.', $html);
    }

    public function test_formats_parentheticals_inside_dialogue_block(): void
    {
        $fountain = "JUAN\n(en voz baja)\nNo des un paso más.";

        $html = ScreenplayFormatterService::formatToHtml($fountain);

        $this->assertStringContainsString('screenplay-dialogue-block', $html);
        $this->assertStringContainsString('screenplay-character', $html);
        $this->assertStringContainsString('screenplay-parenthetical', $html);
        $this->assertStringContainsString('(en voz baja)', $html);
        $this->assertStringContainsString('No des un paso más.', $html);
    }

    public function test_formats_slugline_and_transitions(): void
    {
        $fountain = "INT. BOSQUE - NOCHE\n\nCORTE A:";

        $html = ScreenplayFormatterService::formatToHtml($fountain);

        $this->assertStringContainsString('screenplay-slugline', $html);
        $this->assertStringContainsString('INT. BOSQUE - NOCHE', $html);
        $this->assertStringContainsString('screenplay-transition', $html);
        $this->assertStringContainsString('CORTE A:', $html);
    }
}
