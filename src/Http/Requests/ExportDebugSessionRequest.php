<?php

namespace Akhtar\LaravelDebugTracer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportDebugSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string'],
        ];
    }

    public function traceToken(): ?string
    {
        return normalize_debug_token(
            $this->input('token')
                ?: $this->bearerToken()
                ?: $this->header(config('debug-tracer.matching_header', 'X-Debug-Token'))
        );
    }
}
