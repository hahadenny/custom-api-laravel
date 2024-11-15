<?php

namespace App\Services\Workflow\Steps;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as PhpSpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SpreadsheetImport extends Step
{
    use SpreadsheetTrait;

    protected function getSheetValues(array $data): Collection
    {
        $reader = new Xlsx();

        try {
            $sheetNames = $reader->listWorksheetNames($data['file']);
        } catch (PhpSpreadsheetReaderException $e) {
            throw ValidationException::withMessages(['file' => 'Cannot read the spreadsheet file.']);
        }

        if (empty($sheetNames)) {
            return new Collection();
        }

        $reader->setLoadSheetsOnly(array_values($sheetNames)[0]);
        $sheet = $reader->load($data['file']);

        return $this->collectSheet($sheet);
    }

    protected function validateParamData(array $data): array
    {
        return Validator::validate($data, [
            'file' => 'required|file',
        ]);
    }

    protected function collectSheet(Spreadsheet $sheet): Collection
    {
        $rows = $sheet->getActiveSheet()->toArray();

        if (empty($rows)) {
            return Collection::make();
        }

        $header = array_shift($rows);

        return Collection::make($rows)->map(function ($item) use ($header) {
            $row = Collection::make($item)->pad(count($header), '');

            return Collection::make($header)->combine($row);
        });
    }
}
