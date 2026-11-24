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
