<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="API Madnezz",
 *         description="API com arquitetura Domain-Driven Design e controle de acesso organizacional hierárquico",
 *         @OA\Contact(
 *             email="admin@madnezz.com"
 *         )
 *     ),
 *     @OA\Server(
 *         url="http://localhost:9000/api",
 *         description="Servidor local de desenvolvimento"
 *     ),
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT",
 *         description="Insira o token JWT Bearer"
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="OrganizationContext",
 *     type="object",
 *     title="Contexto Organizacional",
 *     description="Informações do contexto organizacional do usuário",
 *     @OA\Property(property="organization_id", type="string", format="uuid", description="UUID of the organization"),
 *     @OA\Property(property="organization_name", type="string", description="Name of the organization"),
 *     @OA\Property(property="organization_code", type="string", description="Organization code"),
 *     @OA\Property(property="position_level", type="string", enum={"go", "gr", "store_manager"}, description="User's position level in hierarchy"),
 *     @OA\Property(property="organization_unit_id", type="string", format="uuid", description="UUID of the organization unit"),
 *     @OA\Property(property="organization_unit_name", type="string", description="Name of the organization unit"),
 *     @OA\Property(property="organization_unit_type", type="string", enum={"company", "regional", "store"}, description="Type of organization unit"),
 *     @OA\Property(property="departments", type="array", @OA\Items(type="string"), description="List of departments user has access to"),
 *     @OA\Property(property="position_id", type="string", format="uuid", description="UUID of the user's position")
 * )
 */

/**
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     title="Success Response",
 *     description="Standard success response format",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object", description="Response data")
 * )
 */

/**
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Error Response",
 *     description="Standard error response format",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="List of error details")
 * )
 */

/**
 * @OA\Schema(
 *     schema="HierarchyLevel",
 *     type="string",
 *     title="Hierarchy Level",
 *     description="Organizational hierarchy levels",
 *     enum={"go", "gr", "store_manager"},
 *     example="go"
 * )
 */

/**
 * @OA\Schema(
 *     schema="DepartmentType",
 *     type="string",
 *     title="Department Type",
 *     description="Available department types in the organization",
 *     enum={"administrative", "financial", "marketing", "operations", "trade", "macro"},
 *     example="administrative"
 * )
 */

/**
 * @OA\Schema(
 *     schema="OrganizationUnitType",
 *     type="string",
 *     title="Organization Unit Type",
 *     description="Types of organization units",
 *     enum={"company", "regional", "store"},
 *     example="company"
 * )
 */

/**
 * @OA\Tag(
 *     name="Autenticação",
 *     description="Endpoints de autenticação de usuário"
 * )
 */

/**
 * @OA\Tag(
 *     name="Contexto Organizacional",
 *     description="Endpoints para recuperar o contexto organizacional do usuário"
 * )
 */

/**
 * @OA\Tag(
 *     name="Acesso Hierárquico - Nível GO",
 *     description="Endpoints acessíveis apenas por usuários nível GO (Diretor)"
 * )
 */

/**
 * @OA\Tag(
 *     name="Acesso Hierárquico - Nível GR",
 *     description="Endpoints acessíveis por usuários nível GR (Gerente Regional) e superiores"
 * )
 */

/**
 * @OA\Tag(
 *     name="Acesso Hierárquico - Nível Loja",
 *     description="Endpoints acessíveis por todos os níveis hierárquicos (Gerente de Loja e superiores)"
 * )
 */

/**
 * @OA\Tag(
 *     name="Acesso por Departamento",
 *     description="Endpoints com controle de acesso específico por departamento"
 * )
 */

/**
 * @OA\Tag(
 *     name="Acesso Específico a Recursos",
 *     description="Endpoints com validação automática de acesso específico a recursos"
 * )
 */

/**
 * @OA\Tag(
 *     name="Controle de Acesso Combinado",
 *     description="Endpoints que requerem tanto nível hierárquico quanto acesso por departamento"
 * )
 */

class SwaggerDefinitions
{
    // This class exists only to hold Swagger annotations
}