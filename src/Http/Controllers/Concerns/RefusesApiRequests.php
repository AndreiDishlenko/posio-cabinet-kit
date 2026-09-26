<?php

namespace Posio\CabinetKit\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait RefusesApiRequests
{
    // Текст отказа кладётся и в поле error, которое клиент API кабинета выводит тостом,
    // и в message, которое отдал бы штатный отказ фреймворка: так видят его оба клиента.
    protected function refuse(int $status, string $message): JsonResponse
    {
        return response()->json(['error' => $message, 'message' => $message], $status);
    }
}
