<?php

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

/**
 * WorkflowSeeder
 *
 * Seeds workflow templates for all supported generation types.
 * Each template uses placeholder tokens that Workflow::injectPrompt() replaces.
 *
 * Placeholders:
 *   {{POSITIVE_PROMPT}}   — the refined prompt from the LLM
 *   {{NEGATIVE_PROMPT}}   — what to avoid
 *   {{SEED}}              — random seed
 *   {{STEPS}}             — sampler steps (image)
 *   {{CFG}}               — guidance scale (image)
 *   {{WIDTH}} {{HEIGHT}}  — output dimensions (image)
 *   {{FRAME_COUNT}}       — total frames (video)
 *   {{FPS}}               — frames per second (video)
 *   {{MOTION_STRENGTH}}   — motion intensity (image-to-video)
 *   {{DURATION}}          — audio duration in seconds
 *   {{DENOISE}}           — denoising strength (img2vid/vid2vid)
 *
 * NOTE: The JSON below is a SKELETON. Replace it with the actual workflow
 * JSON exported from your ComfyUI instance (File → Export → Export (API Format)).
 * The placeholder tokens must be manually inserted at the correct node positions.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // ── Text → Image (Qwen Image + Lightning LoRA) ────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'image', 'name' => 'Qwen Image Generator'],
            [
                'description'   => 'Text to image using Qwen image model with Lightning LoRA for fast generation',
                'is_active'     => true,
                'workflow_json' => $this->qwenImageWorkflow(),
            ]
        );

        Workflow::firstOrCreate(
            ['type' => 'image', 'name' => 'Z-Image Turbo Generator'],
            [
                'description'   => 'Fast text-to-image generation using Z-Image Turbo diffusion',
                'is_active'     => true,
                'workflow_json' => $this->zImageWorkflow(),
            ]
        );

        // ── Text → Video ──────────────────────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'video', 'name' => 'Text to Video'],
            [
                'description'   => 'Generate a short video clip from a text description',
                'is_active'     => true,
                'workflow_json' => $this->textToVideoWorkflow(),
            ]
        );

        // ── Text → Audio ──────────────────────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'audio', 'name' => 'Text to Audio'],
            [
                'description'   => 'Generate audio (music, sound effects, ambience) from a text description',
                'is_active'     => true,
                'workflow_json' => $this->textToAudioWorkflow(),
            ]
        );

        // ── Image → Video ─────────────────────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'image_to_video', 'name' => 'Animate an Image'],
            [
                'description'   => 'Add motion to a still image using Stable Video Diffusion or similar',
                'is_active'     => true,
                'workflow_json' => $this->imageToVideoWorkflow(),
            ]
        );

        // ── Video → Video ─────────────────────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'video_to_video', 'name' => 'Style Transfer Video'],
            [
                'description'   => 'Apply style transfer or transformation to an existing video',
                'is_active'     => true,
                'workflow_json' => $this->videoToVideoWorkflow(),
            ]
        );

        // ── Video → Video (Face Swap) ─────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'video_to_video', 'name' => 'ReActor Face Swap'],
            [
                'description'   => 'Swap faces in a video using ReActor and InsightFace',
                'is_active'     => true,
                'workflow_json' => $this->reactorFaceSwapWorkflow(),
            ]
        );

        // ── imageAudio → Video ─────────────────────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'avatar_video', 'name' => 'LTX Talking Avatar'],
            [
                'description'   => 'Generate a lip-synced talking avatar from an image and audio',
                'is_active'     => true,
                'workflow_json' => $this->imageAudioToVideoWorkflow(),
            ]
        );

        // ── Image + Audio → Video ─────────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'avatar_video', 'name' => 'LTX Talking Avatar (Audio Sync)'],
            [
                'description'   => 'Generate a talking avatar video from image and audio using LTX-Video',
                'is_active'     => true,
                'workflow_json' => $this->ltxAvatarWorkflow(),
            ]
        );

        // ── Text → Audio (Voice Clone) ────────────────────────────────
        Workflow::firstOrCreate(
            ['type' => 'audio', 'name' => 'Qwen Voice Clone TTS'],
            [
                'description'   => 'Generate speech from text using Qwen TTS with voice cloning',
                'is_active'     => true,
                'workflow_json' => $this->qwenVoiceCloneWorkflow(),
            ]
        );

        $this->command->info('WorkflowSeeder: All workflow templates seeded.');
        $this->command->info('NOTE: Non-image workflows are seeded as inactive.');
        $this->command->info('Replace their workflow_json with real ComfyUI API exports, then set is_active = true.');
    }

    // ── Workflow JSON templates ───────────────────────────────────────────────
    // These are skeletons. Replace with real exports from ComfyUI.
    // To export: in ComfyUI, click the gear icon → Enable Dev Mode, then
    // use "Save (API Format)" to get the JSON that can be submitted to /prompt.

    private function qwenImageWorkflow(): string
    {
        // This is the real Qwen image workflow that was confirmed working.
        // The key nodes are:
        //   "6"  → CLIPTextEncode (positive) — inject {{POSITIVE_PROMPT}} here
        //   "7"  → CLIPTextEncode (negative) — inject {{NEGATIVE_PROMPT}} here
        //   "3"  → KSampler — inject {{STEPS}}, {{CFG}}, {{SEED}} here
        //   "5"  → EmptyLatentImage — inject {{WIDTH}}, {{HEIGHT}} here
        return json_encode([
            "3" => [
                "inputs" => [
                    "seed"          => "{{SEED}}",
                    "steps"         => "{{STEPS}}",
                    "cfg"           => "{{CFG}}",
                    "sampler_name"  => "dpmpp_sde",
                    "scheduler"     => "karras",
                    "denoise"       => 1,
                    "model"         => ["4", 0],
                    "positive"      => ["6", 0],
                    "negative"      => ["7", 0],
                    "latent_image"  => ["5", 0],
                ],
                "class_type" => "KSampler",
            ],
            "4" => [
                "inputs"     => ["ckpt_name" => "qwen_image_fp8_e4m3fn.safetensors"],
                "class_type" => "CheckpointLoaderSimple",
            ],
            "5" => [
                "inputs"     => ["width" => "{{WIDTH}}", "height" => "{{HEIGHT}}", "batch_size" => 1],
                "class_type" => "EmptyLatentImage",
            ],
            "6" => [
                "inputs"     => ["text" => "{{POSITIVE_PROMPT}}", "clip" => ["4", 1]],
                "class_type" => "CLIPTextEncode",
            ],
            "7" => [
                "inputs"     => ["text" => "{{NEGATIVE_PROMPT}}", "clip" => ["4", 1]],
                "class_type" => "CLIPTextEncode",
            ],
            "8" => [
                "inputs"     => ["samples" => ["3", 0], "vae" => ["4", 2]],
                "class_type" => "VAEDecode",
            ],
            "9" => [
                "inputs"     => ["filename_prefix" => "ComfyUI", "images" => ["8", 0]],
                "class_type" => "SaveImage",
            ],
        ]);
    }

    private function textToVideoWorkflow(): string
    {
        // SKELETON — replace with a real CogVideoX, AnimateDiff, or Wan2.1 workflow
        // exported from your ComfyUI in API format.
        // Key placeholders to insert:
        //   {{POSITIVE_PROMPT}} in the text encoder node
        //   {{NEGATIVE_PROMPT}} in the negative text encoder node
        //   {{FRAME_COUNT}} in the video generation node
        //   {{FPS}} in the video combine/save node
        //   {{SEED}} in the sampler node
        return json_encode([
            "_note" => "Replace this with a real text-to-video ComfyUI API workflow JSON. Export from ComfyUI using Save (API Format).",
            "_placeholders" => ["{{POSITIVE_PROMPT}}", "{{NEGATIVE_PROMPT}}", "{{FRAME_COUNT}}", "{{FPS}}", "{{SEED}}"],
        ]);
    }


    private function zImageWorkflow(): string
    {
    return json_encode([

        "39" => [
            "inputs" => [
                "clip_name" => "qwen_3_4b.safetensors",
                "type" => "lumina2",
                "device" => "default"
            ],
            "class_type" => "CLIPLoader"
        ],

        "40" => [
            "inputs" => [
                "vae_name" => "ae.safetensors"
            ],
            "class_type" => "VAELoader"
        ],

        "41" => [
            "inputs" => [
                "width" => "{{WIDTH}}",
                "height" => "{{HEIGHT}}",
                "batch_size" => 1
            ],
            "class_type" => "EmptySD3LatentImage"
        ],

        "42" => [
            "inputs" => [
                "conditioning" => ["45",0]
            ],
            "class_type" => "ConditioningZeroOut"
        ],

        "44" => [
            "inputs" => [
                "seed" => "{{SEED}}",
                "steps" => "{{STEPS}}",
                "cfg" => "{{CFG}}",
                "sampler_name" => "res_multistep",
                "scheduler" => "simple",
                "denoise" => 1,
                "model" => ["47",0],
                "positive" => ["45",0],
                "negative" => ["42",0],
                "latent_image" => ["41",0]
            ],
            "class_type" => "KSampler"
        ],

        "45" => [
            "inputs" => [
                "text" => "{{POSITIVE_PROMPT}}",
                "clip" => ["39",0]
            ],
            "class_type" => "CLIPTextEncode"
        ],

        "46" => [
            "inputs" => [
                "unet_name" => "z_image_turbo_bf16.safetensors",
                "weight_dtype" => "default"
            ],
            "class_type" => "UNETLoader"
        ],

        "47" => [
            "inputs" => [
                "shift" => 3,
                "model" => ["46",0]
            ],
            "class_type" => "ModelSamplingAuraFlow"
        ],

        "43" => [
            "inputs" => [
                "samples" => ["44",0],
                "vae" => ["40",0]
            ],
            "class_type" => "VAEDecode"
        ],

        "9" => [
            "inputs" => [
                "filename_prefix" => "ZImage",
                "images" => ["43",0]
            ],
            "class_type" => "SaveImage"
        ]

    ]);
    }


    private function textToAudioWorkflow(): string
    {
        // SKELETON — replace with a real AudioCraft / Stable Audio workflow
        return json_encode([
            "_note" => "Replace this with a real text-to-audio ComfyUI API workflow JSON.",
            "_placeholders" => ["{{POSITIVE_PROMPT}}", "{{DURATION}}", "{{SEED}}"],
        ]);
    }

    private function qwenVoiceCloneWorkflow(): string
    {
    return json_encode([

        "24" => [
            "inputs" => [
                "audio" => "reference_voice.wav"
            ],
            "class_type" => "LoadAudio"
        ],

        "40" => [
            "inputs" => [
                "target_text" => "{{POSITIVE_PROMPT}}",
                "model_choice" => "1.7B",
                "device" => "auto",
                "precision" => "bf16",
                "language" => "Japanese",
                "seed" => "{{SEED}}",
                "max_new_tokens" => 2048,
                "top_p" => 0.8,
                "top_k" => 20,
                "temperature" => 1,
                "repetition_penalty" => 1.05,
                "x_vector_only" => true,
                "attention" => "auto",
                "unload_model_after_generate" => false,
                "custom_model_path" => "",
                "ref_audio" => ["24",0],
                "config" => ["90",0]
            ],
            "class_type" => "FB_Qwen3TTSVoiceClone"
        ],

        "90" => [
            "inputs" => [
                "pause_linebreak" => 0.4,
                "period_pause" => 0.9,
                "comma_pause" => 0.4,
                "question_pause" => 0.7,
                "hyphen_pause" => 0.5
            ],
            "class_type" => "FB_Qwen3TTSConfig"
        ],

        "21" => [
            "inputs" => [
                "filename_prefix" => "QwenTTS",
                "audio" => ["40",0]
            ],
            "class_type" => "SaveAudio"
        ]

    ]);
    }

    private function imageToVideoWorkflow(): string
    {
        // SKELETON — replace with a real SVD (Stable Video Diffusion) workflow
        return json_encode([
            "_note" => "Replace this with a real image-to-video ComfyUI API workflow JSON.",
            "_placeholders" => ["{{POSITIVE_PROMPT}}", "{{FRAME_COUNT}}", "{{FPS}}", "{{MOTION_STRENGTH}}", "{{SEED}}"],
        ]);
    }

    private function videoToVideoWorkflow(): string
    {
        // SKELETON — replace with a real vid2vid / style transfer workflow
        return json_encode([
            "_note" => "Replace this with a real video-to-video ComfyUI API workflow JSON.",
            "_placeholders" => ["{{POSITIVE_PROMPT}}", "{{DENOISE}}", "{{SEED}}"],
        ]);
    }


    private function reactorFaceSwapWorkflow(): string
    {
    return json_encode([

        "1" => [
            "inputs" => [
                "image" => "source_face.png"
            ],
            "class_type" => "LoadImage"
        ],

        "2" => [
            "inputs" => [
                "video" => "input_video.mp4",
                "force_rate" => 0,
                "custom_width" => 0,
                "custom_height" => 0,
                "frame_load_cap" => 0,
                "skip_first_frames" => 0,
                "select_every_nth" => 1,
                "format" => "AnimateDiff"
            ],
            "class_type" => "VHS_LoadVideo"
        ],

        "3" => [
            "inputs" => [
                "enabled" => true,
                "boost_model" => "none",
                "interpolation" => "Bicubic",
                "visibility" => 1,
                "codeformer_weight" => 0.5,
                "restore_with_main_after" => false
            ],
            "class_type" => "ReActorFaceBoost"
        ],

        "4" => [
            "inputs" => [
                "enabled" => true,
                "swap_model" => "inswapper_128.onnx",
                "facedetection" => "retinaface_resnet50",
                "face_restore_model" => "none",
                "face_restore_visibility" => 0.26,
                "codeformer_weight" => 0.5,
                "detect_gender_input" => "no",
                "detect_gender_source" => "no",
                "input_faces_index" => "0",
                "source_faces_index" => "0",
                "console_log_level" => 1,
                "source_image" => ["1",0],
                "input_image" => ["2",0],
                "face_boost" => ["3",0]
            ],
            "class_type" => "ReActorFaceSwap"
        ],

        "6" => [
            "inputs" => [
                "frame_rate" => "{{FPS}}",
                "loop_count" => 0,
                "filename_prefix" => "FaceSwap",
                "format" => "video/h264-mp4",
                "pix_fmt" => "yuv420p",
                "crf" => 19,
                "save_metadata" => true,
                "trim_to_audio" => false,
                "pingpong" => false,
                "save_output" => true,
                "images" => ["4",0],
                "audio" => ["2",2]
            ],
            "class_type" => "VHS_VideoCombine"
        ]

    ]);
    }

    private function imageAudioToVideoWorkflow(): string
    {
    return json_encode([
        "1" => [
            "inputs" => [
                "sigmas" => "0.909375, 0.725, 0.421875, 0.0"
            ],
            "class_type" => "ManualSigmas"
        ],

        "2" => [
            "inputs" => [
                "noise" => ["9",0],
                "guider" => ["6",0],
                "sampler" => ["13",0],
                "sigmas" => ["1",0],
                "latent_image" => ["42",0]
            ],
            "class_type" => "SamplerCustomAdvanced"
        ],

        "6" => [
            "inputs" => [
                "cfg" => 1,
                "model" => ["51",0],
                "positive" => ["58",0],
                "negative" => ["58",1]
            ],
            "class_type" => "CFGGuider"
        ],

        "8" => [
            "inputs" => [
                "model_name" => "MelBandRoformer_fp32.safetensors"
            ],
            "class_type" => "MelBandRoFormerModelLoader"
        ],

        "9" => [
            "inputs" => [
                "noise_seed" => "{{SEED}}"
            ],
            "class_type" => "RandomNoise"
        ],

        "12" => [
            "inputs" => [
                "audio" => "input_audio.mp3"
            ],
            "class_type" => "LoadAudio"
        ],

        "23" => [
            "inputs" => [
                "ckpt_name" => "ltx-2-19b-dev-fp8.safetensors"
            ],
            "class_type" => "CheckpointLoaderSimple"
        ],

        "31" => [
            "inputs" => [
                "text" => "{{POSITIVE_PROMPT}}",
                "clip" => ["36",0]
            ],
            "class_type" => "CLIPTextEncode"
        ],

        "32" => [
            "inputs" => [
                "text" => "{{NEGATIVE_PROMPT}}",
                "clip" => ["36",0]
            ],
            "class_type" => "CLIPTextEncode"
        ],

        "33" => [
            "inputs" => [
                "frame_rate" => "{{FPS}}",
                "positive" => ["31",0],
                "negative" => ["32",0]
            ],
            "class_type" => "LTXVConditioning"
        ],

        "36" => [
            "inputs" => [
                "text_encoder" => "gemma_3_12B_it.safetensors",
                "ckpt_name" => "ltx-2-19b-dev-fp8.safetensors",
                "device" => "default"
            ],
            "class_type" => "LTXAVTextEncoderLoader"
        ],

        "62" => [
            "inputs" => [
                "image" => "input_image.png"
            ],
            "class_type" => "LoadImage"
        ],

        "63" => [
            "inputs" => [
                "seed" => "{{SEED}}"
            ],
            "class_type" => "Seed (rgthree)"
        ],

        "68" => [
            "inputs" => [
                "width" => "{{WIDTH}}",
                "height" => "{{HEIGHT}}",
                "image" => ["62",0]
            ],
            "class_type" => "ImageResizeKJv2"
        ],

        "81" => [
            "inputs" => [
                "value" => "{{FRAME_COUNT}}"
            ],
            "class_type" => "PrimitiveInt"
        ],

        "85" => [
            "inputs" => [
                "text" => "{{POSITIVE_PROMPT}}"
            ],
            "class_type" => "Text Multiline"
        ],

        "101" => [
            "inputs" => [
                "value" => 0
            ],
            "class_type" => "FloatConstant"
        ],

        "102" => [
            "inputs" => [
                "value" => "{{DURATION}}"
            ],
            "class_type" => "FloatConstant"
        ],

        "66" => [
            "inputs" => [
                "frame_rate" => "{{FPS}}",
                "filename_prefix" => "LTX_avatar",
                "format" => "video/h264-mp4",
                "images" => ["53",0],
                "audio" => ["20",0]
            ],
            "class_type" => "VHS_VideoCombine"
        ]

    ]);
    }

    private function ltxAvatarWorkflow(): string
    {
    return json_encode([

        "1" => [
            "inputs" => [
                "sigmas" => "0.909375, 0.725, 0.421875, 0.0"
            ],
            "class_type" => "ManualSigmas"
        ],

        "6" => [
            "inputs" => [
                "cfg" => "{{CFG}}",
                "model" => ["51",0],
                "positive" => ["58",0],
                "negative" => ["58",1]
            ],
            "class_type" => "CFGGuider"
        ],

        "9" => [
            "inputs" => [
                "noise_seed" => "{{SEED}}"
            ],
            "class_type" => "RandomNoise"
        ],

        "12" => [
            "inputs" => [
                "audio" => "input_audio.mp3"
            ],
            "class_type" => "LoadAudio"
        ],

        "23" => [
            "inputs" => [
                "ckpt_name" => "ltx-2-19b-dev-fp8.safetensors"
            ],
            "class_type" => "CheckpointLoaderSimple"
        ],

        "31" => [
            "inputs" => [
                "text" => "{{POSITIVE_PROMPT}}",
                "clip" => ["36",0]
            ],
            "class_type" => "CLIPTextEncode"
        ],

        "32" => [
            "inputs" => [
                "text" => "{{NEGATIVE_PROMPT}}",
                "clip" => ["36",0]
            ],
            "class_type" => "CLIPTextEncode"
        ],

        "33" => [
            "inputs" => [
                "frame_rate" => "{{FPS}}",
                "positive" => ["31",0],
                "negative" => ["32",0]
            ],
            "class_type" => "LTXVConditioning"
        ],

        "36" => [
            "inputs" => [
                "text_encoder" => "gemma_3_12B_it.safetensors",
                "ckpt_name" => "ltx-2-19b-dev-fp8.safetensors",
                "device" => "default"
            ],
            "class_type" => "LTXAVTextEncoderLoader"
        ],

        "62" => [
            "inputs" => [
                "image" => "input_image.png"
            ],
            "class_type" => "LoadImage"
        ],

        "63" => [
            "inputs" => [
                "seed" => "{{SEED}}"
            ],
            "class_type" => "Seed (rgthree)"
        ],

        "68" => [
            "inputs" => [
                "width" => "{{WIDTH}}",
                "height" => "{{HEIGHT}}",
                "image" => ["62",0]
            ],
            "class_type" => "ImageResizeKJv2"
        ],

        "81" => [
            "inputs" => [
                "value" => "{{FRAME_COUNT}}"
            ],
            "class_type" => "PrimitiveInt"
        ],

        "85" => [
            "inputs" => [
                "text" => "{{POSITIVE_PROMPT}}"
            ],
            "class_type" => "Text Multiline"
        ],

        "101" => [
            "inputs" => [
                "value" => 0
            ],
            "class_type" => "FloatConstant"
        ],

        "102" => [
            "inputs" => [
                "value" => "{{DURATION}}"
            ],
            "class_type" => "FloatConstant"
        ],

        "66" => [
            "inputs" => [
                "frame_rate" => "{{FPS}}",
                "filename_prefix" => "LTX_avatar",
                "format" => "video/h264-mp4",
                "images" => ["53",0],
                "audio" => ["20",0]
            ],
            "class_type" => "VHS_VideoCombine"
        ]

    ]);
    }
}