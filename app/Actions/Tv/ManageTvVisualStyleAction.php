<?php

declare(strict_types=1);

namespace App\Actions\Tv;

use App\Models\TvVisualStyle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class ManageTvVisualStyleAction
{
    public function ensureDefaults(): void
    {
        if (TvVisualStyle::query()->count() > 0) {
            return;
        }

        TvVisualStyle::create([
            'name' => 'Storyboard Clásico',
            'slug' => 'storyboard-clasico',
            'prompt_base' => 'Classic hand-drawn film production storyboard illustration, expressive ink and graphite linework, clean chiaroscuro shading, 16:9 widescreen, strictly non-photorealistic, no text, no panels, no borders',
            'characteristics' => 'Línea artística editorial en blanco y negro o aguada suave, cero fotorrealismo.',
            'is_default' => true,
            'is_active' => true,
        ]);

        TvVisualStyle::create([
            'name' => 'Animación 2D',
            'slug' => 'animacion-2d',
            'prompt_base' => '2D stylized editorial animation frame, clean crisp vector ink outlines, flat cel shading, harmonious artistic color palette, 16:9 full-bleed, strictly non-photorealistic, no text, no watermarks',
            'characteristics' => 'Estilo animación 2D moderna con contornos limpios y color plano expresivo.',
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @return Collection<int, TvVisualStyle>
     */
    public function listActive(): Collection
    {
        $this->ensureDefaults();

        return TvVisualStyle::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, TvVisualStyle>
     */
    public function listAll(): Collection
    {
        $this->ensureDefaults();

        return TvVisualStyle::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?TvVisualStyle $style = null, ?UploadedFile $referenceImage = null): TvVisualStyle
    {
        $name = trim((string) ($data['name'] ?? $style?->name ?? ''));
        $rawSlug = isset($data['slug']) ? trim((string) $data['slug']) : '';
        $slug = $rawSlug !== '' ? Str::slug($rawSlug) : ($style?->slug ?? Str::slug($name));
        if ($slug === '') {
            $slug = 'style-'.Str::lower(Str::random(6));
        }

        $isDefault = array_key_exists('is_default', $data)
            ? (bool) $data['is_default']
            : ($style?->is_default ?? false);

        $isActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($style?->is_active ?? true);

        $payload = [
            'name' => $name,
            'slug' => $slug,
            'prompt_base' => trim((string) ($data['prompt_base'] ?? $style?->prompt_base ?? '')),
            'characteristics' => array_key_exists('characteristics', $data)
                ? ($data['characteristics'] !== null ? trim((string) $data['characteristics']) : null)
                : $style?->characteristics,
            'is_default' => $isDefault,
            'is_active' => $isActive,
        ];

        $storedImagePath = null;
        if ($referenceImage !== null) {
            $storedImagePath = Storage::disk('local')->putFile('tv_styles', $referenceImage);
            $payload['reference_image_path'] = $storedImagePath;
        } elseif (array_key_exists('reference_image_path', $data)) {
            $payload['reference_image_path'] = $data['reference_image_path'];
        }

        $oldImagePath = $style?->reference_image_path;

        $saved = DB::transaction(function () use ($style, $payload, $isDefault): TvVisualStyle {
            if ($isDefault) {
                TvVisualStyle::query()
                    ->when($style !== null, static fn ($query) => $query->where('id', '!=', $style->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            if ($style !== null) {
                $style->update($payload);

                return $style->refresh();
            }

            return TvVisualStyle::create($payload);
        });

        if (
            $storedImagePath !== null
            && is_string($oldImagePath)
            && $oldImagePath !== ''
            && $oldImagePath !== $storedImagePath
            && Storage::disk('local')->exists($oldImagePath)
        ) {
            Storage::disk('local')->delete($oldImagePath);
        }

        return $saved;
    }

    public function setDefault(TvVisualStyle $style): void
    {
        DB::transaction(function () use ($style): void {
            TvVisualStyle::query()
                ->where('id', '!=', $style->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $style->update(['is_default' => true]);
        });
    }

    public function delete(TvVisualStyle $style): void
    {
        $imagePath = $style->reference_image_path;

        $style->delete();

        if (is_string($imagePath) && $imagePath !== '' && Storage::disk('local')->exists($imagePath)) {
            Storage::disk('local')->delete($imagePath);
        }
    }
}
