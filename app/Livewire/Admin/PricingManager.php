<?php

namespace App\Livewire\Admin;

use App\Models\TokenPricingRate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Gestión de Tarifas de Tokens - Administración jG Filmmaker')]
class PricingManager extends Component
{
    public array $editingRates = [];

    // Formulario para añadir nueva tarifa
    public string $new_provider = 'groq';

    public string $new_model = '';

    public float $new_input_cost = 0.50;

    public float $new_output_cost = 0.75;

    public bool $new_is_active = false;

    public function mount(): void
    {
        $this->loadRates();
    }

    public function loadRates(): void
    {
        $rates = TokenPricingRate::orderBy('provider')->orderBy('model')->get();
        foreach ($rates as $rate) {
            $this->editingRates[$rate->id] = [
                'input_cost_per_million' => (float) $rate->input_cost_per_million,
                'output_cost_per_million' => (float) $rate->output_cost_per_million,
            ];
        }
    }

    public function setActiveRate(string $rateId): void
    {
        TokenPricingRate::where('is_active', true)->update(['is_active' => false]);
        $target = TokenPricingRate::findOrFail($rateId);
        $target->is_active = true;
        $target->save();

        session()->flash('status', "Tarifa activa actualizada a {$target->provider} / {$target->model}.");
    }

    public function saveRate(string $rateId): void
    {
        $rate = TokenPricingRate::findOrFail($rateId);

        $inputCost = (float) ($this->editingRates[$rateId]['input_cost_per_million'] ?? 0);
        $outputCost = (float) ($this->editingRates[$rateId]['output_cost_per_million'] ?? 0);

        $rate->input_cost_per_million = max(0, $inputCost);
        $rate->output_cost_per_million = max(0, $outputCost);
        $rate->save();

        session()->flash('status', "Precios actualizados para {$rate->provider} / {$rate->model}.");
    }

    public function createRate(): void
    {
        $this->validate([
            'new_provider' => 'required|string|max:50',
            'new_model' => 'required|string|max:100',
            'new_input_cost' => 'required|numeric|min:0',
            'new_output_cost' => 'required|numeric|min:0',
        ]);

        if ($this->new_is_active) {
            TokenPricingRate::where('is_active', true)->update(['is_active' => false]);
        }

        $created = TokenPricingRate::create([
            'provider' => strtolower(trim($this->new_provider)),
            'model' => trim($this->new_model),
            'input_cost_per_million' => $this->new_input_cost,
            'output_cost_per_million' => $this->new_output_cost,
            'is_active' => $this->new_is_active,
        ]);

        $this->reset(['new_model', 'new_input_cost', 'new_output_cost', 'new_is_active']);
        $this->loadRates();

        session()->flash('status', "Nueva tarifa para {$created->provider} / {$created->model} registrada con éxito.");
    }

    public function deleteRate(string $rateId): void
    {
        $rate = TokenPricingRate::findOrFail($rateId);
        $rate->delete();
        $this->loadRates();

        session()->flash('status', 'Tarifa eliminada del catálogo.');
    }

    public function render()
    {
        $rates = TokenPricingRate::orderBy('provider')->orderBy('model')->get();

        return view('livewire.admin.pricing-manager', [
            'rates' => $rates,
        ]);
    }
}
