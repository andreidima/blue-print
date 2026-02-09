<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mockup extends Model
{
    use HasFactory;

    public const TIP_INFO_GRAFICA = 'info_grafica';
    public const TIP_INFO_MOCKUP = 'info_mockup';
    public const TIP_INFO_TEST = 'info_test';
    public const TIP_INFO_BUN_DE_TIPAR = 'info_bun_de_tipar';

    protected $table = 'mockupuri';

    protected $fillable = [
        'comanda_id',
        'tip',
        'uploaded_by',
        'original_name',
        'path',
        'mime',
        'size',
        'comentariu',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class, 'comanda_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileUrl(): string
    {
        return route('comenzi.mockupuri.view', ['comanda' => $this->comanda_id, 'mockup' => $this->id]);
    }

    public function downloadUrl(): string
    {
        return route('comenzi.mockupuri.download', ['comanda' => $this->comanda_id, 'mockup' => $this->id]);
    }

    public function destroyUrl(): string
    {
        return route('comenzi.mockupuri.destroy', ['comanda' => $this->comanda_id, 'mockup' => $this->id]);
    }

    public static function typeOptions(): array
    {
        return [
            self::TIP_INFO_GRAFICA => 'Info grafica',
            self::TIP_INFO_MOCKUP => 'Info mockup',
            self::TIP_INFO_TEST => 'Info test',
            self::TIP_INFO_BUN_DE_TIPAR => 'Info bun de tipar',
        ];
    }
}
