<?php

namespace App\Livewire\Planning\QualityAssessment;

use App\Models\Project;
use App\Models\Project\Planning\QualityAssessment\Cutoff;
use App\Models\Project\Planning\QualityAssessment\GeneralScore;
use App\Models\Project\Planning\QualityAssessment\Question;
use App\Utils\ToastHelper;
use Livewire\Attributes\On;
use Livewire\Component;

class QuestionRanges extends Component
{
  public $currentProject;
  public $items = [];
  public $oldItems = [];
  public $sum = 0;
  public $intervals = 5;

  protected $rules = [
    'items.*.description' => 'required|string|regex:/^[a-zA-ZÀ-ÿ0-9\s]+$/u',
    'intervals' => 'required|integer|min:2|max:10',
  ];

  protected function messages()
  {
      return [
          'items.*.description.required' => 'O campo descrição é obrigatório.',
          'items.*.description.regex' => 'A descrição só pode conter letras, números e espaços.',
          'intervals.required' => 'O número de intervalos é obrigatório.',
          'intervals.integer' => 'O número de intervalos deve ser um número inteiro.',
          'intervals.min' => 'O número de intervalos deve ser no mínimo 2.',
          'intervals.max' => 'O número de intervalos deve ser no máximo 10.',
      ];
  }

  public function populateItems()
  {
    $projectId = $this->currentProject->id_project;
    $items = GeneralScore::where('id_project', $projectId)->get();

    for ($i = 0; $i < count($items); $i++) {
      $this->items[] = [
        'id_general_score' => $items[$i]->id_general_score,
        'start' => $items[$i]->start,
        'end' => $items[$i]->end,
        'description' => $items[$i]->description
      ];
    }
    $this->oldItems = $this->items;
  }

  public function mount()
  {
    $projectId = request()->segment(2);
    $this->currentProject = Project::findOrFail($projectId);
    $this->sum = Question::where('id_project', $projectId)->sum('weight');
    $this->populateItems();
    $this->intervals = count($this->items) === 0 ? 2 : count($this->items);
  }

  /**
   * Dispatch a toast message to the view.
   */
  public function toast(string $message, string $type)
  {
    $this->dispatch('qa-ranges', ToastHelper::dispatch($type, $message));
  }

  #[On('update-weight-sum')]
  public function updateSum()
  {
    $projectId = $this->currentProject->id_project;
    $this->sum = Question::where('id_project', $projectId)->sum('weight');
  }

  public function updateMin($index, $value)
  {
    try {
      $this->items[$index]['start'] = $value;

      GeneralScore::updateOrCreate([
        'id_general_score' => $this->items[$index]
      ], [
        'start' => $value,
      ]);
        $this->dispatch('general-scores-generated');
    } catch (\Exception $e) {
      $this->toast(
        message: $e->getMessage(),
        type: 'error'
      );
    }
  }

  public function updateMax($index, $value)
  {
    try {
      /**
       * If the new "end" value is the same as the current "end" value,
       * do nothing
       */
      if ($this->items[$index]['end'] == $this->oldItems[$index]['end']) {
        return;
      }

      $this->items[$index]['end'] = round($value, 2);
      $this->items[$index + 1]['start'] = round($value + 0.01, 2);

      /**
       * Update the current "end" value
       */
      GeneralScore::updateOrCreate([
        'id_general_score' => $this->items[$index]['id_general_score']
      ], [
        'end' => $value,
      ]);

      if (count($this->items) <= $index + 1) {
        return;
      }

      /**
       * Update the next "start" value
       */
      GeneralScore::updateOrCreate([
        'id_general_score' => $this->items[$index + 1]['id_general_score']
      ], [
        'start' => round($value + 0.01, 2),
      ]);

        $this->dispatch('general-scores-generated');

      $this->toast(
        message: __('project/planning.quality-assessment.ranges.interval-updated'),
        type: 'success'
      );
      $this->oldItems = $this->items;
    } catch (\Exception $e) {
      $this->toast(
        message: $e->getMessage(),
        type: 'error'
      );
    }
  }

  public function updateLabel($index)
  {
    try {
      $this->validate([
        "items.$index.description" => 'required|string|regex:/^[a-zA-ZÀ-ÿ0-9\s]+$/u',
      ]);

      $idGeneralScore = $this->items[$index]['id_general_score'];
      $value = $this->items[$index]['description'];

      if ($this->oldItems[$index]['description'] === $value) {
        return;
      }

      GeneralScore::updateOrCreate([
        'id_general_score' => $idGeneralScore,
      ], [
        'description' => $value,
      ]);

        $this->dispatch('update-select-minimal-approve');

      $this->toast(
        message: __('project/planning.quality-assessment.ranges.label-updated'),
        type: 'success'
      );

      $this->oldItems[$index]['description'] = $value;
    } catch (\Exception $e) {
      $this->toast(
        message: $e->getMessage(),
        type: 'error'
      );
    }
  }

    public function generateIntervals()
    {
        if ($this->intervals < 2) {
            $this->intervals = 2;
        }

        if ($this->intervals > 10) {
            $this->intervals = 10;
        }

        // Verificar dependências antes de excluir
        $generalScores = GeneralScore::where('id_project', $this->currentProject->id_project)->get();

        foreach ($generalScores as $generalScore) {
            if ($generalScore->papers()->exists()) {
                $this->toast(
                    message: __('project/planning.quality-assessment.ranges.deletion-restricted', [
                        'description' => $generalScore->description,
                    ]),
                    type: 'error'
                );

                return; // Abortar operação
            }
        }

        // Excluir registros antigos de GeneralScore
        GeneralScore::where('id_project', $this->currentProject->id_project)->delete();

        // Gerar novos intervalos
        $sum = $this->sum;
        $items = [];
        $min = 0.01;
        $max = round($sum / $this->intervals, 2);

        for ($i = 0; $i < $this->intervals; $i++) {
            $itemToAdd = [
                'start' => $min,
                'end' => $max,
                'description' => 'Item ' . ($i + 1),
                'id_project' => $this->currentProject->id_project,
            ];

            $itemCreated = GeneralScore::create($itemToAdd);
            $items[] = array_merge($itemCreated->toArray(), [
                'id_project' => $this->currentProject->id_project,
            ]);

            $min = round($max + 0.01, 2);
            $max = round($max + $sum / $this->intervals, 2);
        }

        $this->items = $items;
        $this->oldItems = $this->items;

        // Notificar outros componentes sobre a atualização dos intervalos
        $this->dispatch('general-scores-generated');


        $this->toast(
            message: __('project/planning.quality-assessment.ranges.generated'),
            type: 'success'
        );
    }

    public function updated($propertyName)
    {
        if (preg_match('/items\.(\d+)\.description/', $propertyName, $matches)) {
            $index = $matches[1];
            $this->updateLabel($index);
        }
    }

    public function render()
  {
    return view('livewire.planning.quality-assessment.question-ranges');
  }
}
