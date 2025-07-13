<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/v1/organization/context",
     *     summary="Obter contexto organizacional do usuário atual",
     *     description="Recupera o contexto organizacional do usuário autenticado incluindo posição, unidade e informações de departamento",
     *     tags={"Contexto Organizacional"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Contexto organizacional recuperado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="organization_id", type="string", format="uuid", example="612b29b1-06a4-4885-ab98-de099feb6ecd"),
     *                 @OA\Property(property="organization_name", type="string", example="Madnezz Corporation"),
     *                 @OA\Property(property="organization_code", type="string", example="MADNEZZ"),
     *                 @OA\Property(property="position_level", type="string", enum={"go", "gr", "store_manager"}, example="go"),
     *                 @OA\Property(property="organization_unit_id", type="string", format="uuid", example="e1acb852-4489-43d3-847f-ec027d016ccf"),
     *                 @OA\Property(property="organization_unit_name", type="string", example="Madnezz Corporation"),
     *                 @OA\Property(property="organization_unit_type", type="string", enum={"company", "regional", "store"}, example="company"),
     *                 @OA\Property(property="departments", type="array", 
     *                     @OA\Items(type="string", enum={"administrative", "financial", "marketing", "operations", "trade", "macro"}),
     *                     example={"administrative", "financial", "marketing", "operations", "trade", "macro"}
     *                 ),
     *                 @OA\Property(property="position_id", type="string", format="uuid", example="a9da6ad7-8cf2-4f0a-a31a-b5ff49c39c8c")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Usuário não autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuário não autenticado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuário não possui posição ativa em nenhuma organização",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuário não possui posição ativa em nenhuma organização")
     *         )
     *     )
     * )
     */
    public function getContext(Request $request): JsonResponse
    {
        return $this->sendResponse($request->get('organization_context'));
    }

    /**
     * @OA\Get(
     *     path="/v1/organization/dashboard",
     *     summary="Acessar dashboard organizacional nível GO",
     *     description="Dashboard organizacional acessível apenas por usuários nível GO (Diretor)",
     *     tags={"Acesso Hierárquico - Nível GO"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard GO acessado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard GO - visão geral da organização"),
     *             @OA\Property(property="context", type="object", description="Contexto organizacional do usuário")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Nível hierárquico insuficiente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nível hierárquico insuficiente. Necessário: go")
     *         )
     *     )
     * )
     */
    public function goDashboard(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Dashboard GO - visão geral da organização',
            'context' => $request->get('organization_context')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/regional/dashboard",
     *     summary="Acessar dashboard regional nível GR",
     *     description="Dashboard regional acessível por usuários nível GR (Gerente Regional) e superiores",
     *     tags={"Acesso Hierárquico - Nível GR"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard regional acessado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard regional - visão geral da região"),
     *             @OA\Property(property="context", type="object", description="Contexto organizacional do usuário")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Nível hierárquico insuficiente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nível hierárquico insuficiente. Necessário: gr")
     *         )
     *     )
     * )
     */
    public function regionalDashboard(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Dashboard regional - visão geral da região',
            'context' => $request->get('organization_context')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/store/dashboard",
     *     summary="Acessar dashboard da loja",
     *     description="Dashboard da loja acessível por todos os níveis hierárquicos (Gerente de Loja e superiores)",
     *     tags={"Acesso Hierárquico - Nível Loja"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard da loja acessado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard da loja - dados específicos da loja"),
     *             @OA\Property(property="context", type="object", description="Contexto organizacional do usuário")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Nível hierárquico insuficiente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nível hierárquico insuficiente. Necessário: store_manager")
     *         )
     *     )
     * )
     */
    public function storeDashboard(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Dashboard da loja - dados específicos da loja',
            'context' => $request->get('organization_context')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/reports/administrative",
     *     summary="Acessar relatórios do departamento administrativo",
     *     description="Relatórios acessíveis para usuários com acesso ao departamento administrativo",
     *     tags={"Acesso por Departamento"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Relatórios administrativos acessados com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Relatórios do departamento administrativo"),
     *             @OA\Property(property="department", type="string", example="administrative")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado ao departamento",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Acesso negado. Departamento necessário: administrative")
     *         )
     *     )
     * )
     */
    public function administrativeReports(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Relatórios do departamento administrativo',
            'department' => 'administrative'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/reports/financial",
     *     summary="Acessar relatórios do departamento financeiro",
     *     description="Relatórios acessíveis para usuários com acesso ao departamento financeiro",
     *     tags={"Acesso por Departamento"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Relatórios financeiros acessados com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Relatórios do departamento financeiro"),
     *             @OA\Property(property="department", type="string", example="financial")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado ao departamento",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Acesso negado. Departamento necessário: financial")
     *         )
     *     )
     * )
     */
    public function financialReports(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Relatórios do departamento financeiro',
            'department' => 'financial'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/store/{store_id}/details",
     *     summary="Obter detalhes de loja específica",
     *     description="Acessa detalhes de uma loja específica. O acesso é automaticamente validado com base na posição hierárquica do usuário",
     *     tags={"Acesso Específico a Recursos"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="path",
     *         required=true,
     *         description="UUID da loja",
     *         @OA\Schema(type="string", format="uuid", example="488f1116-41ce-4976-9b6f-90ccaf68bede")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhes da loja recuperados com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detalhes da loja: 488f1116-41ce-4976-9b6f-90ccaf68bede"),
     *             @OA\Property(property="store_id", type="string", format="uuid", example="488f1116-41ce-4976-9b6f-90ccaf68bede"),
     *             @OA\Property(property="context", type="object", description="Contexto organizacional do usuário")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado a esta loja",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Acesso negado a esta loja")
     *         )
     *     )
     * )
     */
    public function storeDetails(Request $request, string $storeId): JsonResponse
    {
        return $this->sendResponse([
            'message' => "Detalhes da loja: {$storeId}",
            'store_id' => $storeId,
            'context' => $request->get('organization_context')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/unit/{unit_id}/details",
     *     summary="Obter detalhes de unidade organizacional específica",
     *     description="Acessa detalhes de uma unidade organizacional específica. O acesso é automaticamente validado com base na posição hierárquica do usuário",
     *     tags={"Acesso Específico a Recursos"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="unit_id",
     *         in="path",
     *         required=true,
     *         description="UUID da unidade organizacional",
     *         @OA\Schema(type="string", format="uuid", example="7d777412-9585-4a83-ab04-ebd28aae725e")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalhes da unidade recuperados com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detalhes da unidade: 7d777412-9585-4a83-ab04-ebd28aae725e"),
     *             @OA\Property(property="unit_id", type="string", format="uuid", example="7d777412-9585-4a83-ab04-ebd28aae725e"),
     *             @OA\Property(property="context", type="object", description="Contexto organizacional do usuário")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso negado a esta unidade organizacional",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Acesso negado a esta unidade organizacional")
     *         )
     *     )
     * )
     */
    public function unitDetails(Request $request, string $unitId): JsonResponse
    {
        return $this->sendResponse([
            'message' => "Detalhes da unidade: {$unitId}",
            'unit_id' => $unitId,
            'context' => $request->get('organization_context')
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/campaigns/regional",
     *     summary="Acessar campanhas de marketing regionais",
     *     description="Campanhas de marketing acessíveis para usuários nível GR e superiores com acesso ao departamento de Marketing",
     *     tags={"Controle de Acesso Combinado"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Campanhas regionais acessadas com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Campanhas de marketing regionais (GR+ com depto Marketing)"),
     *             @OA\Property(property="access", type="string", example="Nível GR ou GO com acesso ao departamento de Marketing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Acesso insuficiente - requer nível GR+ e departamento Marketing",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nível hierárquico insuficiente. Necessário: gr")
     *         )
     *     )
     * )
     */
    public function regionalCampaigns(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'message' => 'Campanhas de marketing regionais (GR+ com depto Marketing)',
            'access' => 'Nível GR ou GO com acesso ao departamento de Marketing'
        ]);
    }
}