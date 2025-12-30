<?php

namespace Tests\Feature;

use App\Models\AgendaBlocking;
use App\Models\AgendaException;
use App\Models\AgendaSetting;
use App\Models\Quadra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservaValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2025, 12, 20, 9, 0, 0, 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prepareContext(): Quadra
    {
        $user = User::factory()->create();
        $quadra = Quadra::create([
            'nome' => 'Quadra 1',
            'tipo' => 'society',
            'ativa' => true,
            'ordem' => 1,
            'capacidade' => 10,
        ]);

        AgendaSetting::create([
            'hora_abertura' => '08:00',
            'hora_fechamento' => '22:00',
            'duracao_reserva_minutos' => 60,
            'dias_semana_ativos' => [1, 2, 3, 4, 5, 6, 7],
            'timezone' => 'America/Sao_Paulo',
        ]);

        Sanctum::actingAs($user);

        return $quadra;
    }

    public function test_reserva_em_data_fechada_retorna_422(): void
    {
        $quadra = $this->prepareContext();
        $date = Carbon::now('America/Sao_Paulo')->addDay()->format('Y-m-d');

        AgendaException::create([
            'data' => $date,
            'hora_abertura' => '00:00',
            'hora_fechamento' => '00:00',
            'fechado' => true,
        ]);

        $response = $this->postJson('/api/v1/reservas', [
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Data indisponivel: feriado/fechado.',
            ]);
    }

    public function test_reserva_em_exception_com_horario_especial_funciona(): void
    {
        $quadra = $this->prepareContext();
        $date = Carbon::now('America/Sao_Paulo')->addDays(2)->format('Y-m-d');

        AgendaException::create([
            'data' => $date,
            'hora_abertura' => '10:00',
            'hora_fechamento' => '12:00',
            'fechado' => false,
        ]);

        $response = $this->postJson('/api/v1/reservas', [
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '10:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_reserva_em_data_normal_fora_do_horario_retorna_422(): void
    {
        $quadra = $this->prepareContext();
        $date = Carbon::now('America/Sao_Paulo')->addDays(3)->format('Y-m-d');

        $response = $this->postJson('/api/v1/reservas', [
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '07:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Fora do horario de funcionamento.',
            ]);
    }

    public function test_reserva_em_data_normal_dentro_do_horario_funciona(): void
    {
        $quadra = $this->prepareContext();
        $date = Carbon::now('America/Sao_Paulo')->addDays(4)->format('Y-m-d');

        $response = $this->postJson('/api/v1/reservas', [
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '09:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_reserva_em_horario_bloqueado_retorna_422(): void
    {
        $quadra = $this->prepareContext();
        $date = Carbon::now('America/Sao_Paulo')->addDays(5)->format('Y-m-d');

        AgendaBlocking::create([
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '10:00',
            'hora_fim' => '12:00',
            'motivo' => 'Manutencao',
        ]);

        $response = $this->postJson('/api/v1/reservas', [
            'quadra_id' => $quadra->id,
            'data' => $date,
            'hora_inicio' => '10:00',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Horario bloqueado.',
            ]);
    }
}
