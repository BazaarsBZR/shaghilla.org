<?php
namespace App\Filament\Resources\PublicMoneyReportResource\Pages;
use App\Filament\Resources\PublicMoneyReportResource;
use Filament\Resources\Pages\CreateRecord;
class CreatePublicMoneyReport extends CreateRecord { protected static string $resource = PublicMoneyReportResource::class; protected function mutateFormDataBeforeCreate(array $data): array { if (($data['status'] ?? '') === 'published') { $data['published_at'] ??= now(); $data['reviewed_by'] = auth()->id(); } return $data; } }
