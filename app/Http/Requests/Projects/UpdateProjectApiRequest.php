<?php

declare(strict_types=1);

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectApiRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'min:3', 'max:120'],
            'synopsis' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string', 'in:draft,in_progress,completed'],
            'category' => ['sometimes', 'string', 'in:personal,ai_video'],
            'storyboard_style' => ['nullable', 'string', 'max:64'],
        ];
    }
}
