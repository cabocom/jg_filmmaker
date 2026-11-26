<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcast;

use App\DTOs\Broadcast\BroadcastJobRequestDTO;
use App\Services\Broadcast\TvMediaPathResolver;
use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel_id' => ['required', 'string', 'max:120'],
            'format' => ['required', 'string', 'in:pill,news,debate'],
            'title' => ['required', 'string', 'max:255'],
            'raw_text' => ['required', 'string', 'min:10'],
            'language' => ['sometimes', 'string', 'max:16'],
            'sources' => ['sometimes', 'array'],
            'visual_mode' => ['sometimes', 'string', 'max:64'],
            'callback_url' => ['nullable', 'url', 'max:500'],
            'resolution_profile' => ['sometimes', 'string', 'in:720p30,1080p30'],
            'output_relative_path' => ['nullable', 'string', 'max:400', function (string $attr, mixed $val, \Closure $fail): void {
                if (is_string($val) && trim($val) !== '') {
                    try {
                        app(TvMediaPathResolver::class)->normalizeRelativePath($val, requireMp4Extension: true);
                    } catch (\InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                }
            }],
            'cast_profiles' => ['sometimes', 'array', 'max:10'],
            'cast_profiles.*.name' => ['required_with:cast_profiles', 'string', 'max:120'],
            'cast_profiles.*.bio' => ['nullable', 'string', 'max:1000'],
            'cast_profiles.*.psychology' => ['nullable', 'string', 'max:500'],
            'cast_profiles.*.costume_notes' => ['nullable', 'string', 'max:500'],
            'location_profile' => ['sometimes', 'nullable', 'array'],
            'location_profile.name' => ['nullable', 'string', 'max:120'],
            'location_profile.type' => ['nullable', 'string', 'in:INT,EXT,INT/EXT'],
            'location_profile.art_department_notes' => ['nullable', 'string', 'max:1000'],
            'location_profile.lighting_notes' => ['nullable', 'string', 'max:1000'],
            'location_profile.address_reference' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Convert the validated request to a BroadcastJobRequestDTO.
     */
    public function toDto(): BroadcastJobRequestDTO
    {
        return BroadcastJobRequestDTO::fromArray($this->validated());
    }
}
