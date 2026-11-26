<?php

declare(strict_types=1);

namespace App\Http\Requests\Tv;

use Illuminate\Foundation\Http\FormRequest;

class StoreTvRundownApiRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255', 'required_without:topic_or_url'],
            'topic_or_url' => ['nullable', 'string', 'max:1000', 'required_if:source_type,wikipedia'],
            'source_type' => ['sometimes', 'string', 'in:prompt,wikipedia,manual'],
            'format' => ['sometimes', 'string', 'in:pill,news,debate'],
            'language' => ['sometimes', 'string', 'max:16'],
            'script_text' => ['nullable', 'string', 'max:50000'],
            'visual_credit' => ['nullable', 'string', 'max:255'],
            'sources' => ['sometimes', 'array'],
            'tts_provider' => ['nullable', 'string', 'max:32'],
            'tts_voice' => ['nullable', 'string', 'max:64'],
            'visual_mode' => ['sometimes', 'string', 'max:64'],
            'resolution_profile' => ['sometimes', 'string', 'in:720p30,1080p30'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'scheduled_at' => ['nullable', 'date'],
            'day_schedule_id' => ['nullable', 'uuid', 'exists:tv_day_schedules,id'],
            'channel_config_id' => ['nullable', 'uuid', 'exists:tv_channel_configs,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'dispatch_production' => ['sometimes', 'boolean'],
        ];
    }
}
