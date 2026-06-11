<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\TutorialAttachment;
use App\Models\TutorialItem;
use App\Models\TutorialModule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorialController extends Controller
{
    private const STORAGE_PATH   = 'storage/tutorials/';
    private const MAX_FILE_SIZE_MB = 50;

    // GET /tree - Árbol completo módulos > subtemas > items (navegación)
    public function tree(Request $request)
    {
        try {
            $user = auth()->user();

            $modulesQuery = TutorialModule::with([
                'subtopics' => function ($q) use ($user) {
                    if (!$user->is_internal) {
                        $q->where('status', 1);
                    }
                    $q->orderBy('order')->with([
                        'items' => function ($q2) use ($user) {
                            if (!$user->is_internal) {
                                $q2->where('is_published', true);
                            }
                            $q2->orderBy('order')
                               ->select('id', 'id_tutorial_module', 'id_tutorial_subtopic', 'title', 'is_published', 'order');
                        },
                    ]);
                },
                'items' => function ($q) use ($user) {
                    // Items directos del módulo (sin subtema)
                    $q->whereNull('id_tutorial_subtopic');
                    if (!$user->is_internal) {
                        $q->where('is_published', true);
                    }
                    $q->orderBy('order')
                      ->select('id', 'id_tutorial_module', 'id_tutorial_subtopic', 'title', 'is_published', 'order');
                },
            ])->orderBy('order');

            if (!$user->is_internal) {
                $modulesQuery->where('status', 1);
            }

            $tree = $modulesQuery->get();

            return ApiResponse::create('Árbol de tutoriales traído correctamente', 200, $tree, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Árbol de tutoriales',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el árbol de tutoriales', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Árbol de tutoriales',
            ]);
        }
    }

    // GET / - Listar items de tutorial con filtros
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $isPaginated = $request->has('page') || $request->has('per_page');
            $perPage = $request->get('per_page', 20);

            $query = TutorialItem::with(['module', 'subtopic.module', 'attachments']);

            if (!$user->is_internal) {
                $query->where('is_published', true)
                      ->where(function ($q) {
                          // Items de módulo activo directamente
                          $q->where(function ($qm) {
                              $qm->whereNotNull('id_tutorial_module')
                                 ->whereNull('id_tutorial_subtopic')
                                 ->whereHas('module', fn($m) => $m->where('status', 1));
                          })
                          // Items de subtema activo con módulo activo
                          ->orWhere(function ($qs) {
                              $qs->whereNotNull('id_tutorial_subtopic')
                                 ->whereHas('subtopic', fn($s) => $s->where('status', 1)
                                     ->whereHas('module', fn($m) => $m->where('status', 1)));
                          });
                      });
            }

            if ($request->has('id_tutorial_module')) {
                $query->where('id_tutorial_module', $request->input('id_tutorial_module'));
            }

            if ($request->has('id_tutorial_subtopic')) {
                $query->where('id_tutorial_subtopic', $request->input('id_tutorial_subtopic'));
            }

            if ($request->has('is_published') && $user->is_internal) {
                $query->where('is_published', filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('title', 'like', '%' . $search . '%');
            }

            $query->orderBy('order')->orderBy('id');

            if ($isPaginated) {
                $items = $query->paginate($perPage);
                $meta_data = [
                    'page'      => $items->currentPage(),
                    'per_page'  => $items->perPage(),
                    'total'     => $items->total(),
                    'last_page' => $items->lastPage(),
                ];
                return ApiResponse::paginate('Tutoriales traídos correctamente', 200, $items->items(), $meta_data, [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener tutoriales',
                ]);
            }

            $items = $query->get();
            return ApiResponse::paginate('Tutoriales traídos correctamente', 200, $items, null, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener tutoriales',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener los tutoriales', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener tutoriales',
            ]);
        }
    }

    // GET /{id} - Obtener un tutorial completo con adjuntos
    public function show($id, Request $request)
    {
        try {
            $user = auth()->user();
            $item = TutorialItem::with(['module', 'subtopic.module', 'attachments'])->find($id);

            if (!$item) {
                return ApiResponse::create('Tutorial no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener tutorial',
                ]);
            }

            if (!$user->is_internal && !$item->is_published) {
                return ApiResponse::create('Tutorial no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Obtener tutorial',
                ]);
            }

            return ApiResponse::create('Tutorial traído correctamente', 200, $item, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al obtener el tutorial', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Obtener tutorial',
            ]);
        }
    }

    // POST / - Crear tutorial (solo admin)
    public function store(Request $request)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_tutorial_module'   => 'required_without:id_tutorial_subtopic|nullable|exists:tutorial_modules,id',
                'id_tutorial_subtopic' => 'required_without:id_tutorial_module|nullable|exists:tutorial_subtopics,id',
                'title'                => 'required|string|max:255',
                'content'              => 'nullable|string',
                'is_published'         => 'nullable|boolean',
                'order'                => 'nullable|integer|min:0',
                'attachments.*'        => 'nullable|file|max:' . (self::MAX_FILE_SIZE_MB * 1024),
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear tutorial',
                ]);
            }

            // Exactamente uno de los dos debe estar seteado
            $hasModule   = !empty($request->input('id_tutorial_module'));
            $hasSubtopic = !empty($request->input('id_tutorial_subtopic'));

            if ($hasModule && $hasSubtopic) {
                return ApiResponse::create('Error de validación', 422, [['parent' => ['Debe especificar id_tutorial_module O id_tutorial_subtopic, no ambos.']]], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Crear tutorial',
                ]);
            }

            $data = $validator->validated();
            $data['is_published'] = $data['is_published'] ?? false;
            $data['order']        = $data['order']        ?? 0;

            // Asegurar que el campo no usado quede en null
            if ($hasModule) {
                $data['id_tutorial_subtopic'] = null;
            } else {
                $data['id_tutorial_module'] = null;
            }

            $storagePath = public_path(self::STORAGE_PATH);
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            unset($data['attachments']);
            $item = TutorialItem::create($data);

            if ($request->hasFile('attachments')) {
                $this->saveAttachments($request->file('attachments'), $item->id, $storagePath);
            }

            $item->load('module', 'subtopic.module', 'attachments');

            return ApiResponse::create('Tutorial creado correctamente', 200, $item, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al crear el tutorial', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Crear tutorial',
            ]);
        }
    }

    // POST /{id} - Actualizar tutorial (solo admin)
    public function update(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar tutorial',
                ]);
            }

            $item = TutorialItem::find($id);
            if (!$item) {
                return ApiResponse::create('Tutorial no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_tutorial_module'   => 'sometimes|nullable|exists:tutorial_modules,id',
                'id_tutorial_subtopic' => 'sometimes|nullable|exists:tutorial_subtopics,id',
                'title'                => 'sometimes|string|max:255',
                'content'              => 'nullable|string',
                'is_published'         => 'sometimes|boolean',
                'order'                => 'nullable|integer|min:0',
                'new_attachments.*'    => 'nullable|file|max:' . (self::MAX_FILE_SIZE_MB * 1024),
                'delete_attachments'   => 'nullable|array',
                'delete_attachments.*' => 'integer|exists:tutorial_attachments,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar tutorial',
                ]);
            }

            $data = $validator->validated();

            // Validar que no se envíen ambos padres al mismo tiempo
            $sendingModule   = array_key_exists('id_tutorial_module', $data)   && !is_null($data['id_tutorial_module']);
            $sendingSubtopic = array_key_exists('id_tutorial_subtopic', $data) && !is_null($data['id_tutorial_subtopic']);

            if ($sendingModule && $sendingSubtopic) {
                return ApiResponse::create('Error de validación', 422, [['parent' => ['Debe especificar id_tutorial_module O id_tutorial_subtopic, no ambos.']]], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Actualizar tutorial',
                ]);
            }

            // Si se cambia el padre, limpiar el otro
            if ($sendingModule) {
                $data['id_tutorial_subtopic'] = null;
            } elseif ($sendingSubtopic) {
                $data['id_tutorial_module'] = null;
            }

            $storagePath = public_path(self::STORAGE_PATH);

            if (!empty($data['delete_attachments'])) {
                $toDelete = TutorialAttachment::where('id_tutorial_item', $item->id)
                    ->whereIn('id', $data['delete_attachments'])
                    ->get();
                foreach ($toDelete as $attachment) {
                    @unlink(public_path($attachment->file_path));
                    $attachment->delete();
                }
            }

            if ($request->hasFile('new_attachments')) {
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                }
                $this->saveAttachments($request->file('new_attachments'), $item->id, $storagePath);
            }

            unset($data['delete_attachments'], $data['new_attachments']);
            $item->update($data);
            $item->load('module', 'subtopic.module', 'attachments');

            return ApiResponse::create('Tutorial actualizado correctamente', 200, $item, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al actualizar el tutorial', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Actualizar tutorial',
            ]);
        }
    }

    // DELETE /{id} - Eliminar tutorial (solo admin)
    public function destroy(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar tutorial',
                ]);
            }

            $item = TutorialItem::with('attachments')->find($id);
            if (!$item) {
                return ApiResponse::create('Tutorial no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar tutorial',
                ]);
            }

            foreach ($item->attachments as $attachment) {
                @unlink(public_path($attachment->file_path));
            }

            $item->delete();

            return ApiResponse::create('Tutorial eliminado correctamente', 200, [], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el tutorial', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar tutorial',
            ]);
        }
    }

    // POST /{id}/attachments - Subir adjuntos adicionales (solo admin)
    public function storeAttachments(Request $request, $id)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Subir adjuntos de tutorial',
                ]);
            }

            $item = TutorialItem::find($id);
            if (!$item) {
                return ApiResponse::create('Tutorial no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Subir adjuntos de tutorial',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'attachments'   => 'required|array|min:1',
                'attachments.*' => 'required|file|max:' . (self::MAX_FILE_SIZE_MB * 1024),
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, [$validator->errors()->toArray()], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Subir adjuntos de tutorial',
                ]);
            }

            $storagePath = public_path(self::STORAGE_PATH);
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            $this->saveAttachments($request->file('attachments'), $item->id, $storagePath);
            $item->load('attachments');

            return ApiResponse::create('Adjuntos subidos correctamente', 200, $item->attachments, [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Subir adjuntos de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al subir los adjuntos', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Subir adjuntos de tutorial',
            ]);
        }
    }

    // DELETE /{id}/attachments/{attachmentId} - Eliminar un adjunto (solo admin)
    public function destroyAttachment(Request $request, $id, $attachmentId)
    {
        try {
            if (!auth()->user()->is_internal) {
                return ApiResponse::create('No autorizado', 403, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar adjunto de tutorial',
                ]);
            }

            $attachment = TutorialAttachment::where('id', $attachmentId)
                ->where('id_tutorial_item', $id)
                ->first();

            if (!$attachment) {
                return ApiResponse::create('Adjunto no encontrado', 404, [], [
                    'request'  => $request,
                    'module'   => 'tutorial',
                    'endpoint' => 'Eliminar adjunto de tutorial',
                ]);
            }

            @unlink(public_path($attachment->file_path));
            $attachment->delete();

            return ApiResponse::create('Adjunto eliminado correctamente', 200, [], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar adjunto de tutorial',
            ]);
        } catch (Exception $e) {
            return ApiResponse::create('Error al eliminar el adjunto', 500, ['error' => $e->getMessage()], [
                'request'  => $request,
                'module'   => 'tutorial',
                'endpoint' => 'Eliminar adjunto de tutorial',
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function saveFile($file, string $storagePath): string
    {
        $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $file->move($storagePath, $filename);
        return self::STORAGE_PATH . $filename;
    }

    private function resolveFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if ($mimeType === 'application/pdf')       return 'pdf';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        return 'other';
    }

    private function saveAttachments(array $files, int $itemId, string $storagePath): void
    {
        $order = TutorialAttachment::where('id_tutorial_item', $itemId)->max('order') ?? -1;

        foreach ($files as $file) {
            $order++;
            $mimeType = $file->getMimeType();

            TutorialAttachment::create([
                'id_tutorial_item' => $itemId,
                'file_path'        => $this->saveFile($file, $storagePath),
                'original_name'    => $file->getClientOriginalName(),
                'file_type'        => $this->resolveFileType($mimeType),
                'mime_type'        => $mimeType,
                'size'             => $file->getSize(),
                'order'            => $order,
            ]);
        }
    }
}
