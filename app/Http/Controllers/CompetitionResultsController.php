<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Services\CompetitionRankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Resultados de una competencia: CSV para Excel (UTF-8 con BOM y «;») y versión imprimible (Guardar como PDF). */
class CompetitionResultsController extends Controller
{
    public function __construct(private CompetitionRankingService $rankings) {}

    public function csv(Request $request, Competition $competition): StreamedResponse
    {
        activity('exports')->causedBy($request->user())->event('export')
            ->withProperties(['type' => 'resultados', 'competition_id' => $competition->id])
            ->log("Exportación de resultados de {$competition->name}");

        $competition->load('exercises');
        $number = fn ($n) => $n === null ? '' : number_format((float) $n, 3, ',', '');

        $headers = ['Categoría', 'Puesto', 'Dorsal', 'Apellido', 'Nombre', 'Club'];
        foreach ($competition->exercises as $exercise) {
            array_push($headers, $exercise->name(), 'Puesto '.$exercise->name());
        }
        array_push($headers, 'All-around', 'Completa');

        $filename = 'resultados-'.Str::slug($competition->name).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($competition, $headers, $number) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');

            foreach ($this->rankings->categories($competition) as $category) {
                foreach ($this->rankings->table($competition, $category['level_id'], $category['group_id']) as $row) {
                    $line = [$category['label'], $row->rank, $row->bib_number, $row->last_name, $row->first_name, $row->institution];
                    foreach ($competition->exercises as $exercise) {
                        $result = $row->exercises[$exercise->id] ?? null;
                        array_push($line, $number($result['score'] ?? null), $result['rank'] ?? '');
                    }
                    array_push($line, $number($row->score), $row->incomplete ? 'No' : 'Sí');
                    fputcsv($out, $line, ';');
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Competition $competition): View
    {
        $competition->load('exercises');

        return view('competitions.results-print', [
            'competition' => $competition,
            'tables' => $this->rankings->categories($competition)->map(fn ($category) => [
                'label' => $category['label'],
                'rows' => $this->rankings->table($competition, $category['level_id'], $category['group_id']),
            ]),
        ]);
    }
}
