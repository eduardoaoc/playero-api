<?php

namespace App\Http\Controllers\Api;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Playero API",
 *         version="1.0.0",
 *         description="API oficial do sistema Playero"
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST,
 *         description="Servidor Local"
 *     ),
 *     @OA\Components(
 *         securitySchemes={
 *             @OA\SecurityScheme(
 *                 securityScheme="bearerAuth",
 *                 type="http",
 *                 scheme="bearer",
 *                 bearerFormat="Token"
 *             )
 *         }
 *     ),
 *     @OA\Tag(name="Auth", description="Autenticacao"),
 *     @OA\Tag(name="Users", description="Usuarios"),
 *     @OA\Tag(name="Quadras", description="Quadras"),
 *     @OA\Tag(name="Agenda", description="Configuracao de agenda"),
 *     @OA\Tag(name="Reservas", description="Reservas"),
 *     @OA\Tag(name="Dashboard", description="Dashboard administrativo"),
 *     @OA\Tag(name="Administradores", description="Gestao de administradores"),
 *     @OA\Tag(name="CMS Home", description="Conteudo da Home"),
 *     @OA\Tag(name="Reservas Admin", description="Reservas administrativas")
 * )
 */
class OpenApi {}
