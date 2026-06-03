<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\TutorialAttachment;
use App\Models\TutorialItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorialController extends Controller
{
    private const STORAGE_PATH    = 'storage/tutorials/';
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const MAX_FILE_SIZE_MB = 50; // 50 MB por archivo

    // GET / - Listar items de tutorial con filtros
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $isPaginated = $request->has('page') || $request->has('per_page');
            $perPage = $request->get('per_page', 20);

            $query = TutorialItem::with(['subtopic.module', 'attachments']);

            if (!$user->is_internal) {
                $query->where('is_published', true)
                      ->whereHas('subtopic', fn($q) => $q->where('status', 1)
                          ->whereHas('module', fn($q2) => $q2->where('status', 1)));
            }

            if ($request->has('id_tutorial_subtopic')) {
                $query->where('id_tutorial_subtopic', $request->input('id_tutorial_subtopic'));
            }

            if ($request->has('id_tutorial_module')) {
                $query->whereHas('subtopic', fn($q) =>
                    $q->where('id_tutorial_module', $request->input('id_tutorial_module'))
                );
            }

            if ($request->has('content_type')) {
                $query->where('content_type', $request->input('content_type'));
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
            $item = TutorialItem::with(['subtopic.module', 'attachments'])->find($id);

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
                'id_tutorial_subtopic' => 'required|exists:tutorial_subtopics,id',
                'title'                => 'required|string|max:255',
                'content'              => 'nullable|string',
                'content_type'         => 'nullable|integer|in:1,2,3,4,5',
                'cover_image'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
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

            $data = $validator->validated();
            $data['content_type'] = $data['content_type'] ?? 1;
            $data['is_published'] = $data['is_published'] ?? false;
            $data['order']        = $data['order']        ?? 0;

            $storagePath = public_path(self::STORAGE_PATH);
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            // Imagen de portada
            if ($request->hasFile('cover_image')) {
                $data['cover_image'] = $this->saveFile($request->file('cover_image'), $storagePath);
            }

            unset($data['attachments']);
            $item = TutorialItem::create($data);

            // Adjuntos
            if ($request->hasFile('attachments')) {
                $this->saveAttachments($request->file('attachments'), $item->id, $storagePath);
            }

            $item->load('subtopic.module', 'attachments');

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
                'id_tutorial_subtopic' => 'sometimes|exists:tutorial_subtopics,id',
                'title'                => 'sometimes|string|max:255',
                'content'              => 'nullable|string',
                'content_type'         => 'sometimes|integer|in:1,2,3,4,5',
                'cover_image'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'remove_cover_image'   => 'nullable|boolean',
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
            $storagePath = public_path(self::STORAGE_PATH);

            // Eliminar adjuntos marcados para borrar
            if (!empty($data['delete_attachments'])) {
                $toDelete = TutorialAttachment::where('id_tutorial_item', $item->id)
                    ->whereIn('id', $data['delete_attachments'])
                    ->get();
                foreach ($toDelete as $attachment) {
                    @unlink(public_path($attachment->file_path));
                    $attachment->delete();
                }
            }

            // Imagen de portada nueva
            if ($request->hasFile('cover_image')) {
                if ($item->cover_image) {
                    @unlink(public_path($item->cover_image));
                }
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                }
                $data['cover_image'] = $this->saveFile($request->file('cover_image'), $storagePath);
            } elseif (!empty($data['remove_cover_image'])) {
                if ($item->cover_image) {
                    @unlink(public_path($item->cover_image));
                }
                $data['cover_image'] = null;
            }

            // Nuevos adjuntos adicionales
            if ($request->hasFile('new_attachments')) {
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                }
                $this->saveAttachments($request->file('new_attachments'), $item->id, $storagePath);
            }

            unset($data['delete_attachments'], $data['new_attachments'], $data['remove_cover_image']);
            $item->update($data);
            $item->load('subtopic.module', 'attachments');

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

            // Eliminar archivos físicos antes de borrar el registro
            foreach ($item->attachments as $attachment) {
                @unlink(public_path($attachment->file_path));
            }
            if ($item->cover_image) {
                @unlink(public_path($item->cover_image));
            }

            $item->delete(); // cascade elimina attachments en DB

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

    // POST /{id}/attachments - Subir adjuntos adicionales a un tutorial existente (solo admin)
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

    // DELETE /{id}/attachments/{attachmentId} - Eliminar un adjunto individual (solo admin)
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

    // GET /tree - Árbol completo módulos > subtemas > items (navegación)
    public function tree(Request $request)
    {
        try {
            $user = auth()->user();

            $modulesQuery = \App\Models\TutorialModule::with([
                'subtopics' => function ($q) use ($user) {
                    if (!$user->is_internal) {
                        $q->where('status', 1);
                    }
                    $q->orderBy('order')->with([
                        'items' => function ($q2) use ($user) {
                            if (!$user->is_internal) {
                                $q2->where('is_published', true);
                            }
                            $q2->orderBy('order')->select('id', 'id_tutorial_subtopic', 'title', 'content_type', 'is_published', 'order');
                        },
                    ]);
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
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        return 'other';
    }

    private function saveAttachments(array $files, int $itemId, string $storagePath): void
    {
        $order = TutorialAttachment::where('id_tutorial_item', $itemId)->max('order') ?? -1;

        foreach ($files as $file) {
            $order++;
            $mimeType = $file->getMimeType();
            $filePath = $this->saveFile($file, $storagePath);

            TutorialAttachment::create([
                'id_tutorial_item' => $itemId,
                'file_path'        => $filePath,
                'original_name'    => $file->getClientOriginalName(),
                'file_type'        => $this->resolveFileType($mimeType),
                'mime_type'        => $mimeType,
                'size'             => $file->getSize(),
                'order'            => $order,
            ]);
        }
    }
}
