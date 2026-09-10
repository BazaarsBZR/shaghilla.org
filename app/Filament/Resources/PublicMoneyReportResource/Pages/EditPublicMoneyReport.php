<?php
namespace App\Filament\Resources\PublicMoneyReportResource\Pages;
use App\Filament\Resources\PublicMoneyReportResource;
use Filament\Resources\Pages\EditRecord;
class EditPublicMoneyReport extends EditRecord { protected static string $resource = PublicMoneyReportResource::class; protected function mutateFormDataBeforeSave(array $data): array { if (($data['status'] ?? '') === 'published') { $data['published_at'] ??= now(); $data['reviewed_by'] = auth()->id(); } return $data; } }
