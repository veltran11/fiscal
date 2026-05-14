<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Cliente;

class ClientesController extends BaseController
{
    private Cliente $model;

    public function __construct()
    {
        $this->model = new Cliente();
    }

    public function index(Request $request, array $params): void
    {
        $payload  = $this->requireAuth($request);
        $clientes = $this->model->allByUser((int) $payload['sub']);
        Response::success($clientes);
    }

    public function store(Request $request, array $params): void
    {
        $payload = $this->requireAuth($request);
        $userId  = (int) $payload['sub'];

        $nombre = trim($request->body('nombre') ?? '');
        if ($nombre === '') {
            Response::error('El nombre es obligatorio.');
        }

        $data = ['usuario_id' => $userId, 'nombre' => $nombre];
        foreach (['tipo_documento', 'numero_documento', 'condicion_iva',
                  'email', 'telefono', 'direccion', 'localidad', 'codigo_postal'] as $campo) {
            $v = $request->body($campo);
            if ($v !== null) $data[$campo] = $v;
        }

        $id      = $this->model->create($data);
        $cliente = $this->model->findForUser($id, $userId);
        Response::success($cliente, 'Cliente creado.');
    }

    public function update(Request $request, array $params): void
    {
        $payload = $this->requireAuth($request);
        $userId  = (int) $payload['sub'];
        $id      = (int) ($params['id'] ?? 0);

        if (!$this->model->findForUser($id, $userId)) {
            Response::error('Cliente no encontrado.', 404);
        }

        $data = [];
        foreach (['nombre', 'tipo_documento', 'numero_documento', 'condicion_iva',
                  'email', 'telefono', 'direccion', 'localidad', 'codigo_postal'] as $campo) {
            $v = $request->body($campo);
            if ($v !== null) $data[$campo] = $v;
        }

        if (isset($data['nombre']) && trim($data['nombre']) === '') {
            Response::error('El nombre es obligatorio.');
        }

        if (empty($data)) {
            Response::error('No hay datos para actualizar.');
        }

        $this->model->update($id, $data);
        $cliente = $this->model->findForUser($id, $userId);
        Response::success($cliente, 'Cliente actualizado.');
    }

    public function destroy(Request $request, array $params): void
    {
        $payload = $this->requireAuth($request);
        $userId  = (int) $payload['sub'];
        $id      = (int) ($params['id'] ?? 0);

        if (!$this->model->findForUser($id, $userId)) {
            Response::error('Cliente no encontrado.', 404);
        }

        $this->model->delete($id);
        Response::success(null, 'Cliente eliminado.');
    }
}
