<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsField;
use App\Models\CmsSection;
use App\Models\Media;
use App\Support\ActivityLogger;
use App\Support\ApiResponse;
use App\Support\CmsSectionPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class HomeController extends Controller
{
    use ApiResponse;

    private const REQUIRED_SECTIONS = [
        'banner_principal',
        'quadras',
        'experiencia',
        'eventos',
        'aulas',
        'area_vip',
    ];

    /**
     * @OA\Put(
     *     path="/api/v1/admin/home",
     *     tags={"CMS Home"},
     *     summary="Atualizar conteudo da Home",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"sections"},
     *                 @OA\Property(
     *                     property="sections",
     *                     type="string",
     *                     example="[{section:banner_principal,order:1,active:true,fields:[{key:titulo,type:text,value:Bem-vindo},{key:imagem,type:image,file:banner_image}]}]"
     *                 ),
     *                 @OA\Property(property="banner_image", type="string", format="binary")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"sections"},
     *                 @OA\Property(
     *                     property="sections",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Conteudo atualizado"),
     *     @OA\Response(response=422, description="Dados invalidos")
     * )
     */
    public function update(Request $request)
    {
        $sectionsPayload = $request->input('sections');

        if (is_string($sectionsPayload)) {
            $decoded = json_decode($sectionsPayload, true);
            if (! is_array($decoded)) {
                return $this->errorResponse('JSON invalido.', 422);
            }
            $sectionsPayload = $decoded;
        }

        if (! is_array($sectionsPayload)) {
            return $this->errorResponse('Dados invalidos.', 422);
        }

        $validator = Validator::make(['sections' => $sectionsPayload], [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.section' => ['required', 'string', 'max:100'],
            'sections.*.order' => ['sometimes', 'integer', 'min:0'],
            'sections.*.active' => ['sometimes', 'boolean'],
            'sections.*.fields' => ['required', 'array', 'min:1'],
            'sections.*.fields.*.key' => ['required', 'string', 'max:100'],
            'sections.*.fields.*.type' => ['required', 'string', Rule::in(CmsField::TYPES)],
            'sections.*.fields.*.value' => ['nullable', 'string'],
            'sections.*.fields.*.order' => ['sometimes', 'integer', 'min:0'],
            'sections.*.fields.*.active' => ['sometimes', 'boolean'],
            'sections.*.fields.*.media_id' => ['sometimes', 'integer', 'exists:media,id'],
            'sections.*.fields.*.file' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Dados invalidos.', 422, $validator->errors());
        }

        $sectionKeys = [];
        foreach ($sectionsPayload as $section) {
            $sectionKey = (string) $section['section'];
            if (isset($sectionKeys[$sectionKey])) {
                return $this->errorResponse('Secao duplicada.', 422);
            }
            $sectionKeys[$sectionKey] = true;

            $fieldKeys = [];
            foreach ($section['fields'] as $field) {
                $fieldKey = (string) $field['key'];
                if (isset($fieldKeys[$fieldKey])) {
                    return $this->errorResponse('Campo duplicado na secao.', 422);
                }
                $fieldKeys[$fieldKey] = true;
            }
        }

        $missingSections = array_values(array_diff(self::REQUIRED_SECTIONS, array_keys($sectionKeys)));
        if (! empty($missingSections)) {
            return $this->errorResponse('Secoes obrigatorias ausentes.', 422, [
                'missing' => $missingSections,
            ]);
        }

        $fileRules = [];
        $fileData = [];
        foreach ($sectionsPayload as $section) {
            foreach ($section['fields'] as $field) {
                if (($field['type'] ?? null) === CmsField::TYPE_IMAGE && ! empty($field['file'])) {
                    $fileKey = (string) $field['file'];
                    $fileRules[$fileKey] = ['required', 'file', 'image', 'max:5120'];
                    $fileData[$fileKey] = $request->file($fileKey);
                }
            }
        }

        if (! empty($fileRules)) {
            $fileValidator = Validator::make($fileData, $fileRules);
            if ($fileValidator->fails()) {
                return $this->errorResponse('Dados invalidos.', 422, $fileValidator->errors());
            }
        }

        DB::transaction(function () use ($sectionsPayload, $request) {
            foreach ($sectionsPayload as $sectionData) {
                $section = CmsSection::firstOrNew(['section' => $sectionData['section']]);
                $section->order = $sectionData['order'] ?? $section->order ?? 0;
                $section->active = $sectionData['active'] ?? $section->active ?? true;
                $section->save();

                foreach ($sectionData['fields'] as $fieldData) {
                    $field = CmsField::firstOrNew([
                        'cms_section_id' => $section->id,
                        'key' => $fieldData['key'],
                    ]);

                    $field->type = $fieldData['type'];
                    $field->order = $fieldData['order'] ?? $field->order ?? 0;
                    $field->active = $fieldData['active'] ?? $field->active ?? true;

                    if (array_key_exists('value', $fieldData)) {
                        $field->value = $fieldData['value'];
                    }

                    if ($fieldData['type'] === CmsField::TYPE_IMAGE) {
                        if (! empty($fieldData['media_id'])) {
                            $field->media_id = (int) $fieldData['media_id'];
                        } elseif (! empty($fieldData['file'])) {
                            $media = $this->storeMedia($request, (string) $fieldData['file']);
                            $field->media_id = $media->id;
                        }
                    } else {
                        $field->media_id = null;
                    }

                    $field->save();
                }
            }
        });

        $sectionCount = count($sectionsPayload);
        $fieldCount = array_sum(array_map(
            fn (array $section) => count($section['fields'] ?? []),
            $sectionsPayload
        ));

        ActivityLogger::log(
            $request,
            'cms_home_updated',
            'Conteudo da Home atualizado.',
            null,
            [
                'sections' => $sectionCount,
                'fields' => $fieldCount,
            ]
        );

        $sections = CmsSection::query()
            ->with(['fields' => function ($query) {
                $query->orderBy('order')
                    ->orderBy('id')
                    ->with('media');
            }])
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'sections' => $sections->map(fn (CmsSection $section) => CmsSectionPresenter::make($section))->all(),
        ], 'Home atualizada com sucesso.');
    }

    private function storeMedia(Request $request, string $fileKey): Media
    {
        $file = $request->file($fileKey);

        $path = $file->store('cms', 'public');

        return Media::create([
            'disk' => 'public',
            'path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?? 0,
        ]);
    }
}
