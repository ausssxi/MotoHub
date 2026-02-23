<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            // --- 基本情報 ---
            if (!Schema::hasColumn('bike_models', 'model_code')) {
                $table->string('model_code')->nullable()->comment('型式 (例: BA-DG09J)');
            }
            if (!Schema::hasColumn('bike_models', 'release_year')) {
                $table->integer('release_year')->nullable()->comment('発売年');
            }

            // --- サイズ・重量 ---
            if (!Schema::hasColumn('bike_models', 'length')) {
                $table->integer('length')->nullable()->comment('全長(mm)');
            }
            if (!Schema::hasColumn('bike_models', 'width')) {
                $table->integer('width')->nullable()->comment('全幅(mm)');
            }
            if (!Schema::hasColumn('bike_models', 'height')) {
                $table->integer('height')->nullable()->comment('全高(mm)');
            }
            if (!Schema::hasColumn('bike_models', 'seat_height')) {
                $table->integer('seat_height')->nullable()->comment('シート高(mm)');
            }
            if (!Schema::hasColumn('bike_models', 'weight')) {
                $table->integer('weight')->nullable()->comment('車両重量(kg)');
            }

            // --- エンジン・燃費 ---
            if (!Schema::hasColumn('bike_models', 'engine_type')) {
                $table->string('engine_type')->nullable()->comment('エンジン種類');
            }
            if (!Schema::hasColumn('bike_models', 'fuel_consumption')) {
                $table->decimal('fuel_consumption', 5, 2)->nullable()->comment('燃費(km/L)');
            }
            if (!Schema::hasColumn('bike_models', 'tank_capacity')) {
                $table->decimal('tank_capacity', 4, 1)->nullable()->comment('タンク容量(L)');
            }
            if (!Schema::hasColumn('bike_models', 'fuel_supply')) {
                $table->string('fuel_supply')->nullable()->comment('燃料供給方式 (キャブレター等)');
            }

            // --- パワー・トルク ---
            if (!Schema::hasColumn('bike_models', 'max_power')) {
                $table->string('max_power')->nullable()->comment('最高出力');
            }
            if (!Schema::hasColumn('bike_models', 'max_torque')) {
                $table->string('max_torque')->nullable()->comment('最大トルク');
            }

            // --- 足回り ---
            if (!Schema::hasColumn('bike_models', 'tire_size_front')) {
                $table->string('tire_size_front')->nullable()->comment('フロントタイヤ');
            }
            if (!Schema::hasColumn('bike_models', 'tire_size_rear')) {
                $table->string('tire_size_rear')->nullable()->comment('リアタイヤ');
            }
            if (!Schema::hasColumn('bike_models', 'brake_type_front')) {
                $table->string('brake_type_front')->nullable()->comment('前ブレーキ');
            }
            if (!Schema::hasColumn('bike_models', 'brake_type_rear')) {
                $table->string('brake_type_rear')->nullable()->comment('後ブレーキ');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bike_models', function (Blueprint $table) {
            $columns = [
                'model_code', 'release_year',
                'length', 'width', 'height', 'seat_height', 'weight',
                'engine_type', 'fuel_consumption', 'tank_capacity', 'fuel_supply',
                'max_power', 'max_torque',
                'tire_size_front', 'tire_size_rear', 'brake_type_front', 'brake_type_rear'
            ];

            // ロールバック時も、カラムが存在する場合のみ削除する安全設計
            foreach ($columns as $column) {
                if (Schema::hasColumn('bike_models', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};