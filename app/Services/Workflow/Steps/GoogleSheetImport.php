<?php

namespace App\Services\Workflow\Steps;

use App\Services\Workflow\WorkflowException;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Revolution\Google\Sheets\Facades\Sheets as FacadeSheets;
use Revolution\Google\Sheets\Sheets;

class GoogleSheetImport extends Step
{
    use SpreadsheetTrait;

    public function getSheetValues(array $data): Collection
    {
        $spreadsheet = FacadeSheets::spreadsheet($this->retrieveSpreadsheetId($data['url']));

        try {
            $sheetList = $spreadsheet->sheetList();
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 403) {
                throw ValidationException::withMessages(['url' => 'Google. Permission to the spreadsheet file is denied.']);
            } elseif ($e->getCode() === 404) {
                throw ValidationException::withMessages(['url' => 'Google. The spreadsheet file was not found.']);
            } else {
                $errorMessage = $this->getErrorMessageToDisplay($e);

                if ($errorMessage !== '') {
                    throw ValidationException::withMessages(['url' => 'Google. '.$errorMessage]);
                }

                throw new WorkflowException('Google. Unknown error.', 0, $e);
            }
        }

        if (empty($sheetList)) {
            return new Collection();
        }

        /** @var \Revolution\Google\Sheets\Sheets $sheet */
        $sheet = $spreadsheet->sheetById(array_keys($sheetList)[0]);

        return $this->collectSheet($sheet);
    }

    protected function validateParamData(array $data): array
    {
        return Validator::validate($data, [
            'url' => 'required|url',
        ]);
    }

    protected function retrieveSpreadsheetId(string $url): string
    {
        $spreadsheetId = explode('/', parse_url($url, PHP_URL_PATH))[3] ?? '';

        if ($spreadsheetId === '') {
            throw ValidationException::withMessages(['url' => 'Google. The spreadsheet id was not found.']);
        }

        return $spreadsheetId;
    }

    protected function collectSheet(Sheets $sheet): Collection
    {
        $rows = $sheet->get();
        $header = $rows->pull(0);
        return FacadeSheets::collection($header, $rows);
    }

    protected function getErrorMessageToDisplay(GoogleServiceException $e): string
    {
        $errorMessage = '';

        foreach ($e->getErrors() as $error) {
            if ($error['message'] === 'API key not valid. Please pass a valid API key.') {
                $errorMessage = $error['message'];
                break;
            }
        }

        return $errorMessage;
    }
}
