<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

use App\Enums\ProjectCategory;

final readonly class CreateProjectDTO
{
    public function __construct(
        public string $title,
        public ?string $synopsis = null,
        public string $status = 'draft',
        public ProjectCategory $category = ProjectCategory::PERSONAL,
        public ?string $storyboardStyle = null,
        public ?string $rawText = null,
        public ?string $slugline = null,
    ) {}
}
