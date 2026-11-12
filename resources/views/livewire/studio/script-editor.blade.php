<div class="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors">
    @include('livewire.studio.partials.editor.header-assets')

    @include('livewire.studio.partials.editor.top-toolbar')

    @if($activeView === 'pipeline')
        @include('livewire.studio.partials.editor.scenes-flow')
    @else
        @include('livewire.studio.partials.editor.consolidated-script')
    @endif

    @include('livewire.studio.partials.editor.block-detail-drawer')

    @include('livewire.studio.partials.editor.crud-modals')

    @include('livewire.studio.partials.editor.token-quota-modals')

    @include('livewire.studio.partials.editor.project-modals')

    @include('livewire.studio.partials.editor.floating-tts-player')

    @include('livewire.studio.partials.editor.storyboard-modals')
</div>
