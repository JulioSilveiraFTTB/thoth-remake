<?php

namespace App\Livewire\Planning\QualityAssessment;

use App\Models\Project;
use App\Models\Project\Planning\QualityAssessment\Cutoff;
use App\Models\Project\Planning\QualityAssessment\Question;
use App\Utils\ToastHelper;
use Livewire\Attributes\On;
use Livewire\Component;

class QuestionCutoff extends Component
{
  public $currentProject;
  public $questions = [];
  public $isCutoffMaxValue;

  public $sum = 0;
  public $cutoff = 0;
  public $oldCutoff = 0;

  public function mount()
  {
    $projectId = request()->segment(2);
    $this->currentProject = Project::findOrFail($projectId);
    $this->questions = Question::where('id_project', $projectId)->get();
    $this->sum = $this->questions->sum('weight');

    if (Cutoff::where('id_project', $projectId)->exists()) {
      $this->cutoff = Cutoff::where('id_project', $projectId)->first()->score;
      $this->oldCutoff = Cutoff::where('id_project', $projectId)->first()->score;
    } else {
      Cutoff::create(['id_project' => $projectId, 'score' => 0]);
      $this->cutoff = 0;
      $this->oldCutoff = 0;
    }
  }

  #[On('update-weight-sum')]
  public function updateSum()
  {
    $projectId = $this->currentProject->id_project;
    $this->questions = Question::where('id_project', $projectId)->get();
    $this->sum = $this->questions->sum('weight');
  }

  #[On('update-cutoff')]
  public function updateSumCutoff()
  {
    $projectId = $this->currentProject->id_project;
    $this->questions = Question::where('id_project', $projectId)->get();
    $this->sum = $this->questions->sum('weight');

    if ($this->cutoff > $this->sum) {
      $this->cutoff = $this->sum;
      Cutoff::updateOrCreate(['id_project' => $projectId], [
        'id_project' => $projectId,
        'score' => $this->cutoff,
      ]);
      $this->isCutoffMaxValue = true;
    }
  }

  /**
   * Dispatch a toast message to the view.
   */
  public function toast(string $message, string $type)
  {
    $this->dispatch('question-cutoff', ToastHelper::dispatch($type, $message));
  }

  public function updateCutoff()
  {
    $cutoff = $this->cutoff;
    $projectId = $this->currentProject->id_project;

    if ($cutoff === $this->oldCutoff) {
      return;
    }

    if ($cutoff < 0 || $cutoff > $this->sum) {
      $this->toast(
        message: 'Invalid cutoff value',
        type: 'info',
      );
      $this->cutoff = $this->oldCutoff;
      return;
    }

    $this->toast(
      message: 'Minimal score to approve updated successfully',
      type: 'success',
    );
    Cutoff::updateOrCreate(['id_project' => $projectId], [
      'id_project' => $projectId,
      'score' => $cutoff,
    ]);
    $this->oldCutoff = $cutoff;
    $this->isCutoffMaxValue = false;
  }

  public function render()
  {
    return view('livewire.planning.quality-assessment.question-cutoff');
  }
}
