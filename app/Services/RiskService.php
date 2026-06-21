<?php

namespace App\Services;

use App\Models\BeneficialOwner;
use App\Models\BusinessCase;
use App\Models\RiskAssessment;
use Illuminate\Support\Arr;

class RiskService
{
    private const HIGH_RISK_REGIONS = ['KY', 'VG', 'BZ', 'PA', 'SC', 'MH'];
    private const HIGH_RISK_INDUSTRIES = ['虚拟货币', '博彩', '贵金属', '文物', '跨境支付', '虚拟资产'];

    public function screen(BusinessCase $case): RiskAssessment
    {
        $business = $case->business;
        $ubos = $case->beneficialOwners;

        $factors = [];
        $score = 0;

        $pepHit = $ubos->contains(fn (BeneficialOwner $o) => $o->is_pep);
        if ($pepHit) {
            $score += 30;
            $factors[] = $this->factor('受益人为政治公众人物(PEP)', 30, true);
        } else {
            $factors[] = $this->factor('受益人为政治公众人物(PEP)', 30, false);
        }

        $sanctionsHit = str_contains($business->name, '受限') || str_contains($business->name, '制裁');
        if ($sanctionsHit) {
            $score += 40;
            $factors[] = $this->factor('命中制裁/限制名单', 40, true);
        } else {
            $factors[] = $this->factor('命中制裁/限制名单', 40, false);
        }

        $adverseMedia = str_contains($business->scope ?? '', '争议') || str_contains($business->name, '争议');
        if ($adverseMedia) {
            $score += 15;
            $factors[] = $this->factor('存在负面新闻/舆情', 15, true);
        } else {
            $factors[] = $this->factor('存在负面新闻/舆情', 15, false);
        }

        $shellCompany = $business->registered_capital && str_contains($business->registered_capital, '1万');
        if ($shellCompany) {
            $score += 20;
            $factors[] = $this->factor('疑似空壳/壳公司特征', 20, true);
        } else {
            $factors[] = $this->factor('疑似空壳/壳公司特征', 20, false);
        }

        if (in_array($business->region, self::HIGH_RISK_REGIONS, true)) {
            $score += 10;
            $factors[] = $this->factor('注册地为高风险司法辖区', 10, true);
        } else {
            $factors[] = $this->factor('注册地为高风险司法辖区', 10, false);
        }

        if (in_array($business->industry, self::HIGH_RISK_INDUSTRIES, true)) {
            $score += 10;
            $factors[] = $this->factor('属于高风险行业', 10, true);
        } else {
            $factors[] = $this->factor('属于高风险行业', 10, false);
        }

        $concentrated = $ubos->contains(fn (BeneficialOwner $o) => $o->ownership_percent >= 50);
        if ($concentrated) {
            $score += 5;
            $factors[] = $this->factor('股权高度集中(≥50%)', 5, true);
        } else {
            $factors[] = $this->factor('股权高度集中(≥50%)', 5, false);
        }

        $score = min(100, $score);
        $level = $this->levelFor($score);

        return RiskAssessment::updateOrCreate(
            ['case_id' => $case->id],
            [
                'score' => $score,
                'level' => $level,
                'pep_hit' => $pepHit,
                'sanctions_hit' => $sanctionsHit,
                'adverse_media' => $adverseMedia,
                'shell_company' => $shellCompany,
                'factors' => $factors,
                'screened_at' => now(),
            ],
        );
    }

    public function applyToCase(BusinessCase $case): void
    {
        $assessment = $this->screen($case);
        $case->risk_score = $assessment->score;
        $case->risk_level = $assessment->level;
        $case->save();
    }

    public function levelFor(int $score): string
    {
        return match (true) {
            $score >= 70 => 'prohibited',
            $score >= 45 => 'high',
            $score >= 20 => 'medium',
            default => 'low',
        };
    }

    private function factor(string $label, int $weight, bool $hit): array
    {
        return ['label' => $label, 'weight' => $weight, 'hit' => $hit];
    }
}
