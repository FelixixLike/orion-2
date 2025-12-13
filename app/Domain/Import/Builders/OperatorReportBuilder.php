<?php

declare(strict_types=1);

namespace App\Domain\Import\Builders;

use App\Domain\Import\Models\OperatorReport;
use App\Domain\Import\Models\Simcard;
use Illuminate\Support\Carbon;

class OperatorReportBuilder
{
    private ?int $simcardId = null;
    private ?string $iccid = null; // Nuevo campo

    private ?string $cityCode = null;
    private ?string $coid = null;
    private ?string $phoneNumber = null;
    private ?string $commissionStatus = null;
    private ?Carbon $activationDate = null;
    private ?Carbon $cutoffDate = null;
    private ?float $commissionPaid80 = null;
    private ?float $commissionPaid20 = null;
    private ?float $totalCommission = null;
    private ?float $rechargeAmount = null;
    private ?string $rechargePeriod = null;
    private ?float $paymentPercentage = null;
    private ?string $custcode = null;
    private ?float $totalRechargePerPeriod = null;
    private ?int $importId = null;
    private ?int $periodYear = null;
    private ?int $periodMonth = null;
    private ?string $periodLabel = null;
    private bool $isConsolidated = false;
    private ?array $cutoffNumbers = null;
    private ?float $totalPaid = null;
    private ?float $calculatedAmount = null;
    private ?float $amountDifference = null;
    private ?array $rawPayload = null;
    private ?int $createdBy = null;

    public function withCreatedBy(?int $userId): self
    {
        $this->createdBy = $userId;
        return $this;
    }

    public function withIccid(?string $iccid): self
    {
        $this->iccid = $iccid;
        return $this;
    }

    public function forSimcard(Simcard $simcard): self
    {
        $this->simcardId = $simcard->id;
        return $this;
    }

    public function withCityCode(?string $cityCode): self
    {
        $this->cityCode = $cityCode;
        return $this;
    }

    public function withCoid(?string $coid): self
    {
        $this->coid = $coid;
        return $this;
    }

    public function withPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function withCommissionStatus(?string $commissionStatus): self
    {
        $this->commissionStatus = $commissionStatus;
        return $this;
    }

    public function withActivationDate(?Carbon $activationDate): self
    {
        $this->activationDate = $activationDate;
        return $this;
    }

    public function withCutoffDate(?Carbon $cutoffDate): self
    {
        $this->cutoffDate = $cutoffDate;
        return $this;
    }

    public function withCommissionPaid80(?float $commissionPaid80): self
    {
        $this->commissionPaid80 = $commissionPaid80;
        return $this;
    }

    public function withCommissionPaid20(?float $commissionPaid20): self
    {
        $this->commissionPaid20 = $commissionPaid20;
        return $this;
    }

    public function withTotalCommission(?float $totalCommission): self
    {
        $this->totalCommission = $totalCommission;
        return $this;
    }

    public function withRechargeAmount(?float $rechargeAmount): self
    {
        $this->rechargeAmount = $rechargeAmount;
        return $this;
    }

    public function withRechargePeriod(?string $rechargePeriod): self
    {
        $this->rechargePeriod = $rechargePeriod;
        return $this;
    }

    public function withPeriod(?int $year, ?int $month): self
    {
        $this->periodYear = $year;
        $this->periodMonth = $month;
        if ($year && $month) {
            $this->periodLabel = sprintf('%04d-%02d', $year, $month);
        }
        return $this;
    }

    public function withPaymentPercentage(?float $paymentPercentage): self
    {
        $this->paymentPercentage = $paymentPercentage;
        return $this;
    }

    public function withCustcode(?string $custcode): self
    {
        $this->custcode = $custcode;
        return $this;
    }

    public function withTotalRechargePerPeriod(?float $totalRechargePerPeriod): self
    {
        $this->totalRechargePerPeriod = $totalRechargePerPeriod;
        return $this;
    }

    public function withTotals(?float $totalPaid, ?float $calculated, ?float $difference): self
    {
        $this->totalPaid = $totalPaid;
        $this->calculatedAmount = $calculated;
        $this->amountDifference = $difference;
        return $this;
    }

    public function forImport(int $importId): self
    {
        $this->importId = $importId;
        return $this;
    }

    public function consolidated(bool $isConsolidated = true): self
    {
        $this->isConsolidated = $isConsolidated;
        return $this;
    }

    public function withCutoffNumbers(?array $cutoffNumbers): self
    {
        $this->cutoffNumbers = $cutoffNumbers;
        return $this;
    }

    public function withRawPayload(?array $payload): self
    {
        $this->rawPayload = $payload;
        return $this;
    }

    public function build(): OperatorReport
    {
        $data = [
            'simcard_id' => $this->simcardId,
            'iccid' => $this->iccid, // Nuevo campo
            'phone_number' => $this->phoneNumber,
            'city_code' => $this->cityCode,
            'coid' => $this->coid,
            'commission_status' => $this->commissionStatus,
            'activation_date' => $this->activationDate,
            'cutoff_date' => $this->cutoffDate,
            'commission_paid_80' => $this->commissionPaid80,
            'commission_paid_20' => $this->commissionPaid20,
            'total_commission' => $this->totalCommission,
            'recharge_amount' => $this->rechargeAmount,
            'recharge_period' => $this->rechargePeriod,
            'payment_percentage' => $this->paymentPercentage,
            'custcode' => $this->custcode,
            'total_recharge_per_period' => $this->totalRechargePerPeriod,
            'import_id' => $this->importId,
            'period_year' => $this->periodYear,
            'period_month' => $this->periodMonth,
            'period_label' => $this->periodLabel,
            'is_consolidated' => $this->isConsolidated,
            'cutoff_numbers' => $this->cutoffNumbers,
            'total_paid' => $this->totalPaid,
            'calculated_amount' => $this->calculatedAmount,
            'amount_difference' => $this->amountDifference,
            'raw_payload' => $this->rawPayload,
            'created_by' => $this->createdBy,
        ];

        \Illuminate\Support\Facades\Log::info('OperatorReportBuilder: DATOS A INSERTAR/ACTUALIZAR EN BD', $data);

        // CAMBIO CRÍTICO: Usar create explícito.
        // updateOrCreate estaba causando que imports nuevos sobrescribieran registros de imports anteriores
        // (robando su propiedad import_id) o colapsaran múltiples filas sin COID en una sola.
        // La limpieza de re-ejecuciones del MISMO import_id ya se hace en ImportProcessorService.
        return OperatorReport::create($data);
    }
}
