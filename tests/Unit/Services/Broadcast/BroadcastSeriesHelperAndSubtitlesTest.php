<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Broadcast;

use App\Services\Broadcast\BroadcastSeriesHelper;
use App\Services\Broadcast\ShotSubtitleGenerator;
use PHPUnit\Framework\TestCase;

class BroadcastSeriesHelperAndSubtitlesTest extends TestCase
{
    public function test_series_name_for_format_maps_supported_broadcast_formats(): void
    {
        $this->assertSame('Breves Biografías IA', BroadcastSeriesHelper::seriesNameForFormat('bio'));
        $this->assertSame('Píldoras Tech IA', BroadcastSeriesHelper::seriesNameForFormat('pill'));
        $this->assertSame('Píldoras Tech IA', BroadcastSeriesHelper::seriesNameForFormat('pildora'));
        $this->assertSame('Noticias AI Today', BroadcastSeriesHelper::seriesNameForFormat('news'));
        $this->assertSame('Noticias AI Today', BroadcastSeriesHelper::seriesNameForFormat('noticia'));
        $this->assertSame('Debates AI Today', BroadcastSeriesHelper::seriesNameForFormat('debate'));
        $this->assertSame('AI Today TV', BroadcastSeriesHelper::seriesNameForFormat('other'));
        $this->assertSame('AI Today TV', BroadcastSeriesHelper::seriesNameForFormat(null));
    }

    public function test_build_lower_third_text_formats_two_ass_lines_omitting_empty_metadata(): void
    {
        $full = BroadcastSeriesHelper::buildLowerThirdText(
            'bio',
            'Ada Lovelace',
            'https://es.wikipedia.org/wiki/Ada_Lovelace',
            'CC BY-SA 4.0',
        );
        $this->assertSame('Breves Biografías IA · Ada Lovelace\Nhttps://es.wikipedia.org/wiki/Ada_Lovelace · CC BY-SA 4.0', $full);

        $onlySource = BroadcastSeriesHelper::buildLowerThirdText('news', 'Noticias', 'https://example.com', null);
        $this->assertSame('Noticias AI Today · Noticias\Nhttps://example.com', $onlySource);

        $noMeta = BroadcastSeriesHelper::buildLowerThirdText('debate', 'Debate IA', null, '  ');
        $this->assertSame('Debates AI Today · Debate IA', $noMeta);
    }

    public function test_shot_subtitle_generator_configures_bottom_left_lower_third_style_and_raised_default_margin(): void
    {
        $generator = new ShotSubtitleGenerator;
        $assPath = sys_get_temp_dir().'/test_lower_third_'.uniqid().'.ass';

        $lowerThird = BroadcastSeriesHelper::buildLowerThirdText(
            'bio',
            'Alan Turing',
            'https://es.wikipedia.org/wiki/Alan_Turing',
            'Public Domain',
        );

        try {
            $generator->generateGlobalAss([
                ['start_seconds' => 0.0, 'duration_seconds' => 4.0, 'oral_text' => 'Hola mundo'],
            ], $assPath, $lowerThird);

            $content = file_get_contents($assPath);

            $this->assertIsString($content);
            $this->assertStringContainsString('Style: Default,Arial,48,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3,1,2,20,20,95,1', $content);
            $this->assertStringContainsString('Style: Credit,Arial,22,&H00FFFFFF,&H000000FF,&H80000000,&H80000000,0,0,0,0,100,100,0,0,3,2,0,1,36,36,24,1', $content);
            $this->assertStringContainsString('Breves Biografías IA · Alan Turing\Nhttps://es.wikipedia.org/wiki/Alan_Turing · Public Domain', $content);
        } finally {
            @unlink($assPath);
        }
    }

    public function test_shot_subtitle_generator_uses_26pt_credit_font_size_for_vertical_shorts(): void
    {
        $generator = new ShotSubtitleGenerator;
        $assPath = sys_get_temp_dir().'/test_vertical_credit_'.uniqid().'.ass';

        try {
            $generator->generateAss('Texto de prueba', 3.0, $assPath, verticalShort: true, visualCredit: 'AI Today TV · Test');
            $content = file_get_contents($assPath);

            $this->assertIsString($content);
            $this->assertStringContainsString('Style: Credit,Arial,26,', $content);
        } finally {
            @unlink($assPath);
        }
    }
}
