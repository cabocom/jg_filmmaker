<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcast;

use App\DTOs\Broadcast\BroadcastJobRequestDTO;
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
     * @return array<string, array<int, string>>
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
