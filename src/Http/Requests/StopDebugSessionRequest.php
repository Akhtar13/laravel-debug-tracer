<?php

namespace Akhtar\LaravelDebugTracer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StopDebugSessionRequest extends FormRequest
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
            'session_id' => ['required', 'string'],
        ];
    }

    public function sessionId(): string
    {
        return (string) $this->validated('session_id');
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
