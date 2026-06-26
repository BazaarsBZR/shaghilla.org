<?php

namespace Database\Seeders;

use App\Models\KeywordRule;
use Illuminate\Database\Seeder;

class KeywordRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workerKeywords = [
            'عامل',
            'عمال',
            'العمل',
            'وظائف',
            'وظيفة',
            'توظيف',
            'أجور',
            'رواتب',
            'الحد الأدنى للأجور',
            'ضمان',
            'الضمان الاجتماعي',
            'تعويض',
            'تقاعد',
            'بطالة',
            'دوام',
            'حقوق العمال',
            'نقابة',
            'نقابات',
            'اتحاد العمال',
            'اتحاد',
            'إضراب',
            'اعتصام',
            'احتجاج',
            'تظاهرة',
            'تحرك عمالي',
            'مطالب عمالية',
            'وزارة العمل',
            'تفتيش العمل',
            'قانون العمل',
            'الصندوق الوطني للضمان',
        ];

        $breakingKeywords = [
            'عاجل',
            'الآن',
            'بيروت',
            'لبنان',
            'إضراب',
            'اعتصام',
            'احتجاج',
            'قطع طرق',
            'تصعيد',
            'مواجهات',
        ];

        foreach ($workerKeywords as $keyword) {
            KeywordRule::updateOrCreate(
                ['type' => KeywordRule::TYPE_WORKER, 'keyword' => $keyword],
                ['is_active' => true],
            );
        }

        foreach ($breakingKeywords as $keyword) {
            KeywordRule::updateOrCreate(
                ['type' => KeywordRule::TYPE_BREAKING, 'keyword' => $keyword],
                ['is_active' => true],
            );
        }
    }
}
