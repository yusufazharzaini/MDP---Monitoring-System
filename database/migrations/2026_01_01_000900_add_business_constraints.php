<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level business constraints (ERD requirement 20).
 *
 * Validation already happens in Form Requests and services; these constraints
 * are the last line of defence, so a bad import, a console command or a manual
 * SQL edit cannot leave the warehouse with a negative receipt or a thirteenth
 * month.
 *
 * CHECK constraints are applied on MySQL and PostgreSQL only. SQLite - which
 * backs the test suite - cannot add a CHECK to an existing table, and silently
 * accepts (then ignores) the statement, so it is skipped explicitly rather than
 * pretended. The same invariants are covered there by the service-layer tests.
 */
return new class extends Migration
{
    /**
     * table => [constraint name => expression]
     *
     * @var array<string, array<string, string>>
     */
    private const CHECKS = [
        'purchase_order_items' => [
            'chk_poi_qty_ordered_positive' => 'qty_ordered > 0',
            'chk_poi_qty_received_not_negative' => 'qty_received >= 0',
            'chk_poi_unit_price_not_negative' => 'unit_price >= 0',
            'chk_poi_amount_not_negative' => 'amount >= 0',
            'chk_poi_line_no_positive' => 'line_no > 0',
            'chk_poi_receipt_window' => '(first_receipt_date IS NULL OR last_receipt_date IS NULL OR last_receipt_date >= first_receipt_date)',
        ],
        'delivery_items' => [
            'chk_di_qty_received_not_negative' => 'qty_received >= 0',
            'chk_di_days_late_not_negative' => 'days_late >= 0',
        ],
        'purchase_orders' => [
            'chk_po_total_amount_not_negative' => 'total_amount >= 0',
        ],
        'suppliers' => [
            'chk_supplier_lead_time_not_negative' => 'lead_time_days >= 0',
        ],
        'materials' => [
            'chk_material_minimum_stock_not_negative' => 'minimum_stock >= 0',
            'chk_material_critical_stock_not_negative' => 'critical_stock >= 0',
            'chk_material_lead_time_not_negative' => 'lead_time_days >= 0',
        ],
        'delivery_problems' => [
            'chk_problem_due_after_report' => '(due_date IS NULL OR due_date >= problem_date)',
        ],
        'corrective_actions' => [
            'chk_action_due_after_action_date' => '(due_date IS NULL OR due_date >= action_date)',
        ],
        'supplier_evaluations' => [
            'chk_eval_month_range' => 'period_month BETWEEN 1 AND 12',
            'chk_eval_year_range' => 'period_year BETWEEN 2000 AND 2100',
            'chk_eval_delivery_score_range' => 'delivery_score BETWEEN 0 AND 100',
            'chk_eval_quality_score_range' => 'quality_score BETWEEN 0 AND 100',
            'chk_eval_quantity_score_range' => 'quantity_score BETWEEN 0 AND 100',
            'chk_eval_responsiveness_score_range' => 'responsiveness_score BETWEEN 0 AND 100',
            'chk_eval_total_score_range' => 'total_score BETWEEN 0 AND 100',
        ],
        'supplier_evaluation_items' => [
            'chk_eval_item_weight_range' => 'weight BETWEEN 0 AND 100',
            'chk_eval_item_score_not_negative' => 'score >= 0',
        ],
        'kpi_settings' => [
            'chk_kpi_target_not_negative' => 'target_value >= 0',
        ],
        'problem_attachments' => [
            'chk_attachment_size_positive' => 'file_size > 0',
        ],
    ];

    public function up(): void
    {
        /*
         * One receipt may record a given purchase order line at most once.
         * Without this, two rows for the same line would double-count in every
         * KPI aggregate, because the delivery line is the measurement grain.
         * A partially rejected receipt is modelled as one line carrying the
         * accepted quantity plus a QUALITY_PROBLEM, not as a duplicate row.
         */
        Schema::table('delivery_items', function (Blueprint $table): void {
            $table->unique(['delivery_id', 'purchase_order_item_id'], 'delivery_items_line_unique');

            // Covers the join + status aggregate behind every dashboard KPI card.
            $table->index(
                ['delivery_id', 'timeliness_status', 'quantity_status'],
                'delivery_items_delivery_status_index',
            );
        });

        // A criterion may only be scored once inside one evaluation.
        Schema::table('supplier_evaluation_items', function (Blueprint $table): void {
            $table->unique(['supplier_evaluation_id', 'criteria_name'], 'evaluation_items_criteria_unique');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            // Critical-material rule: shortages inside a period.
            $table->index(['fulfillment_status', 'schedule_delivery_date'], 'po_items_fulfillment_schedule_index');
            // Material-scoped PO monitoring.
            $table->index(['material_id', 'schedule_delivery_date'], 'po_items_material_schedule_index');
        });

        Schema::table('delivery_problems', function (Blueprint $table): void {
            // Critical-material rule: CRITICAL severity problems inside a period.
            $table->index(['severity', 'problem_date'], 'problems_severity_date_index');
        });

        $this->applyChecks();
    }

    public function down(): void
    {
        $this->dropChecks();

        Schema::table('delivery_problems', function (Blueprint $table): void {
            $table->dropIndex('problems_severity_date_index');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropIndex('po_items_material_schedule_index');
            $table->dropIndex('po_items_fulfillment_schedule_index');
        });

        Schema::table('supplier_evaluation_items', function (Blueprint $table): void {
            $table->dropUnique('evaluation_items_criteria_unique');
        });

        Schema::table('delivery_items', function (Blueprint $table): void {
            $table->dropIndex('delivery_items_delivery_status_index');
            $table->dropUnique('delivery_items_line_unique');
        });
    }

    private function applyChecks(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach (self::CHECKS as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)',
                    $this->wrap($table),
                    $this->wrap($name),
                    $expression,
                ));
            }
        }
    }

    private function dropChecks(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        $keyword = DB::getDriverName() === 'mysql' ? 'CHECK' : 'CONSTRAINT';

        foreach (self::CHECKS as $table => $constraints) {
            foreach (array_keys($constraints) as $name) {
                DB::statement(sprintf(
                    'ALTER TABLE %s DROP %s %s',
                    $this->wrap($table),
                    $keyword,
                    $this->wrap($name),
                ));
            }
        }
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }

    private function wrap(string $identifier): string
    {
        return DB::connection()->getQueryGrammar()->wrap($identifier);
    }
};
