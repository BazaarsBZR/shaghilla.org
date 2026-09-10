<?php
namespace App\Filament\Resources\PublicMoneyReportResource\Pages;
use App\Filament\Resources\PublicMoneyReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListPublicMoneyReports extends ListRecords { protected static string $resource = PublicMoneyReportResource::class; protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; } }
