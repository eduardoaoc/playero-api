<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\QuadraController;
use App\Http\Controllers\Api\V1\ReservaController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\HomeController as AdminHomeController;
use App\Http\Controllers\Api\V1\CalendarController;

Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });
    });

    // Quadras (publico)
    Route::get('/quadras', [QuadraController::class, 'index']);

    // Home (CMS publico)
    Route::get('/home', [HomeController::class, 'show']);

    // Users (publico)
    Route::post('/users', [UserController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function () {
        // Reservas (cliente)
        Route::get('/quadras/disponiveis', [ReservaController::class, 'quadrasDisponiveis']);
        Route::get('/disponibilidade', [ReservaController::class, 'disponibilidade']);
        Route::get('/agenda/day', [AgendaController::class, 'dayAvailability']);
        Route::get('/agenda/month', [AgendaController::class, 'monthAvailability']);
        Route::get('/events/calendar', [EventController::class, 'calendar']);
        Route::post('/reservas', [ReservaController::class, 'store']);
        Route::post('/reservas/{id}/cancelar', [ReservaController::class, 'cancel']);
        Route::get('/minhas-reservas', [ReservaController::class, 'minhasReservas']);
    });

    // Users    
    Route::middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
        Route::get('/clients', [UserController::class, 'clients']);
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{id}/password', [UserController::class, 'updatePassword']);
        Route::post('/users/reset-password', [UserController::class, 'resetPassword']);

        // Quadras (admin)
        Route::post('/quadras', [QuadraController::class, 'store']);
        Route::put('/quadras/{id}', [QuadraController::class, 'update']);
        Route::patch('/quadras/{id}/status', [QuadraController::class, 'toggleStatus']);
        Route::delete('/quadras/{id}', [QuadraController::class, 'destroy']);

        // Agenda Config e Bloqueios (admin)
        Route::get('/agenda/config', [AgendaController::class, 'getConfig']);
        Route::put('/agenda/config', [AgendaController::class, 'updateConfig']);
        Route::get('/agenda/exceptions', [AgendaController::class, 'listExceptions']);
        Route::post('/agenda/exceptions', [AgendaController::class, 'storeException']);
        Route::put('/agenda/exceptions/{id}', [AgendaController::class, 'updateException']);
        Route::delete('/agenda/exceptions/{id}', [AgendaController::class, 'deleteException']);
        Route::post('/agenda/blockings', [AgendaController::class, 'storeBlocking']);
        Route::get('/agenda/blockings', [AgendaController::class, 'listBlockings']);
        Route::delete('/agenda/blockings/{id}', [AgendaController::class, 'deleteBlocking']);

        // Calendario geral (admin)
        Route::get('/calendar/overview', [CalendarController::class, 'overview']);
        Route::get('/calendar/day/{date}', [CalendarController::class, 'dayDetail']);
        Route::post('/calendar/exceptions', [CalendarController::class, 'storeException']);
        Route::put('/calendar/exceptions/{id}', [CalendarController::class, 'updateException']);

        // Events (admin)
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);

        // Reservas (admin)
        Route::get('/reservas', [ReservaController::class, 'index']);
    });

    // Painel Admin
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::put('/home', [AdminHomeController::class, 'update']);

        Route::middleware('role:super_admin')->group(function () {
            Route::get('/admins', [AdminController::class, 'index']);
            Route::post('/admins', [AdminController::class, 'store']);
            Route::put('/admins/{id}', [AdminController::class, 'update']);
            Route::patch('/admins/{id}/status', [AdminController::class, 'updateStatus']);
            Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
        });

        Route::get('/reservas', [ReservaController::class, 'index']);
        Route::post('/reservas/{id}/cancelar', [ReservaController::class, 'cancel']);
        Route::post('/reservas', [ReservaController::class, 'store']);
    });
});
