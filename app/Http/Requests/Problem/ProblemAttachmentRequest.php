<?php

declare(strict_types=1);

namespace App\Http\Requests\Problem;

use Illuminate\Foundation\Http\FormRequest;

class ProblemAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('mdp.attachments.max_kilobytes', 5120),
                // Laravel's `mimes` rule checks the extension against the
                // guessed type, so a .php renamed to .pdf fails here.
                // AttachmentService then checks the probed MIME type as well.
                'mimes:'.implode(',', (array) config('mdp.attachments.allowed_mimes', [])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = number_format(((int) config('mdp.attachments.max_kilobytes', 5120)) / 1024, 1);

        return [
            'file.required' => 'Pilih file yang akan dilampirkan.',
            'file.max' => "Ukuran file melebihi batas {$maxMb} MB.",
            'file.mimes' => 'Tipe file tidak diizinkan. Gunakan PDF, gambar, Word atau Excel.',
        ];
    }
}
