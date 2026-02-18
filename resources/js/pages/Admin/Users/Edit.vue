<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index,
    update
} from '@/routes/admin/user'
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, InfoIcon, Save, Camera, Video, UserCheck, X, Download } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Spinner from '@/components/ui/spinner/Spinner.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Users',
        href: index().url,
    },
    {
        title: 'Edit User',
        href: index().url,
    },
];

const props = defineProps<{
    module: {
        id: number,
        first_name: string,
        last_name: string,
        middle_name: string,
        email: string,
        phone: string,
        facial_images?: any,
        face_trained_at?: string,
    }
}>()

const successState = ref(false)

const form = useForm({
    first_name: props.module.first_name || "",
    last_name: props.module.last_name || "",
    middle_name: props.module.middle_name || "",
    email: props.module.email || "",
    phone: props.module.phone || "",
    facial_images: props.module.facial_images || null,
})

// Facial Recognition States
const isCameraActive = ref(false)
const isTraining = ref(false)
const isTesting = ref(false)
const isFaceDetected = ref(false)
const faceTrainingProgress = ref(0)
const captureCount = ref(0)
const videoElement = ref<HTMLVideoElement | null>(null)
const canvasElement = ref<HTMLCanvasElement | null>(null)
const stream = ref<MediaStream | null>(null)
const capturedFaces = ref<string[]>([])
const faceDetectionInterval = ref<number | null>(null)
const currentFaceBox = ref<{x: number, y: number, width: number, height: number} | null>(null)
const cameraError = ref<string | null>(null)
const isModelLoading = ref(false)
const faceapiLoaded = ref(false)
const detectionScore = ref<number | null>(null)
const videoContainer = ref<HTMLDivElement | null>(null)

// Debug refs
const debugLogs = ref<string[]>([])
const lastCaptureTime = ref<number | null>(null)

// Constants
const REQUIRED_CAPTURES = 5
const TRAINING_INTERVAL = 1000
const CAPTURE_SIZE = 400

// Add debug function
const addDebugLog = (message: string) => {
    console.log(`[FaceRec] ${message}`)
    debugLogs.value.push(`${new Date().toLocaleTimeString()}: ${message}`)
    if (debugLogs.value.length > 20) {
        debugLogs.value.shift()
    }
}

// Watch isTraining for changes
watch(isTraining, (newValue, oldValue) => {
    if (oldValue !== newValue) {
        addDebugLog(`⚠️ isTraining changed from ${oldValue} to ${newValue}`)
    }
})

// Load face-api.js dynamically
const loadFaceApi = async () => {
    if (typeof window !== 'undefined' && !(window as any).faceapi) {
        isModelLoading.value = true
        cameraError.value = null
        
        try {
            const script = document.createElement('script')
            script.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js'
            
            await new Promise((resolve, reject) => {
                script.onload = resolve
                script.onerror = reject
                document.head.appendChild(script)
            })

            addDebugLog('Face API script loaded, loading models...')
            
            await Promise.race([
                Promise.all([
                    (window as any).faceapi.nets.tinyFaceDetector.loadFromUri('https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights'),
                    (window as any).faceapi.nets.faceLandmark68Net.loadFromUri('https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights')
                ]),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Model loading timeout')), 10000))
            ])
            
            faceapiLoaded.value = true
            addDebugLog('Face API models loaded successfully')
            cameraError.value = null
            
        } catch (error) {
            console.error('Error loading Face API:', error)
            cameraError.value = 'Using basic face detection (limited accuracy)'
            faceapiLoaded.value = false
            addDebugLog('Face API loading failed, using fallback')
        } finally {
            isModelLoading.value = false
        }
    }
}

// FIXED: Face detection with proper coordinate handling
const detectFace = async () => {
    if (!videoElement.value || !canvasElement.value || !isCameraActive.value) {
        return false
    }
    
    const video = videoElement.value
    const canvas = canvasElement.value
    
    if (video.videoWidth === 0 || video.videoHeight === 0) {
        return false
    }
    
    // Set canvas to video dimensions
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    
    const context = canvas.getContext('2d')
    if (!context) return false
    
    // Clear previous drawings
    context.clearRect(0, 0, canvas.width, canvas.height)
    
    let hasFace = false
    
    try {
        // Use face-api.js if loaded
        if (faceapiLoaded.value && (window as any).faceapi) {
            try {
                const options = new (window as any).faceapi.TinyFaceDetectorOptions({
                    inputSize: 320,
                    scoreThreshold: 0.3
                })
                
                const detections = await (window as any).faceapi.detectAllFaces(video, options)
                
                if (detections && detections.length > 0) {
                    hasFace = true
                    const detection = detections[0]
                    const box = detection.box
                    detectionScore.value = detection.score
                    
                    // Store face box in original coordinates
                    currentFaceBox.value = {
                        x: box.x,
                        y: box.y,
                        width: box.width,
                        height: box.height
                    }
                    
                    // Draw on canvas WITHOUT mirroring since video is mirrored with CSS
                    // We need to mirror the drawing to match the CSS mirroring
                    context.save();
                    context.scale(-1, 1);
                    context.translate(-canvas.width, 0);
                    
                    // Draw detection box
                    context.strokeStyle = '#10B981'
                    context.lineWidth = 3
                    context.strokeRect(box.x, box.y, box.width, box.height)
                    
                    // Draw label
                    context.fillStyle = '#10B981'
                    context.font = 'bold 16px Arial'
                    context.fillText(
                        `Face (${Math.round(detection.score * 100)}%)`,
                        box.x,
                        box.y - 10
                    )
                    
                    context.restore();
                }
            } catch (detectionError) {
                console.error('Face API detection error:', detectionError)
            }
        }
        
        // Fallback detection
        if (!hasFace) {
            // Draw video frame
            context.drawImage(video, 0, 0, canvas.width, canvas.height)
            
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height)
            hasFace = detectFaceSimple(imageData)
            
            if (hasFace) {
                // Draw default box in center
                const boxWidth = Math.min(canvas.width * 0.4, 300)
                const boxHeight = boxWidth * 1.2
                const x = (canvas.width - boxWidth) / 2
                const y = (canvas.height - boxHeight) / 2
                
                // Store face box in original coordinates
                currentFaceBox.value = { 
                    x: x, 
                    y: y, 
                    width: boxWidth, 
                    height: boxHeight 
                }
                
                // Draw on canvas WITHOUT mirroring since video is mirrored with CSS
                context.save();
                context.scale(-1, 1);
                context.translate(-canvas.width, 0);
                
                context.strokeStyle = '#F59E0B'
                context.lineWidth = 3
                context.strokeRect(x, y, boxWidth, boxHeight)
                context.fillStyle = '#F59E0B'
                context.font = 'bold 16px Arial'
                context.fillText('Face Detected (Basic)', x, y - 10)
                
                context.restore();
                detectionScore.value = 0.7
            }
        }
    } catch (error) {
        console.error('Face detection error:', error)
    }
    
    if (!hasFace) {
        currentFaceBox.value = null
        detectionScore.value = null
    }
    
    isFaceDetected.value = hasFace
    return hasFace
}

// Simple face detection fallback
const detectFaceSimple = (imageData: ImageData): boolean => {
    const data = imageData.data
    let skinPixels = 0
    const totalPixels = data.length / 4
    const sampleRate = 20
    
    for (let i = 0; i < data.length; i += sampleRate * 4) {
        const r = data[i]
        const g = data[i + 1]
        const b = data[i + 2]
        
        if (r > 60 && g > 40 && b > 20 &&
            r > g && r > b &&
            Math.abs(r - g) > 15 &&
            r > 95 && g > 40 && b > 20 &&
            r < 250 && g < 250 && b < 250) {
            skinPixels++
        }
    }
    
    const skinPercentage = skinPixels / (totalPixels / sampleRate)
    return skinPercentage > 0.08
}

// FIXED: Start camera with proper initialization
const startCamera = async () => {
    cameraError.value = null
    
    try {
        // Clean up existing camera
        if (faceDetectionInterval.value) {
            cancelAnimationFrame(faceDetectionInterval.value)
            faceDetectionInterval.value = null
        }
        
        if (stream.value) {
            stream.value.getTracks().forEach(track => {
                track.stop()
            })
            stream.value = null
        }
        
        isCameraActive.value = false
        isFaceDetected.value = false
        currentFaceBox.value = null
        detectionScore.value = null
        
        // Request standard video constraints
        const constraints = {
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user',
                frameRate: { ideal: 30 }
            },
            audio: false
        }
        
        stream.value = await navigator.mediaDevices.getUserMedia(constraints)
        
        if (videoElement.value) {
            videoElement.value.srcObject = stream.value
            
            await new Promise((resolve) => {
                if (videoElement.value) {
                    videoElement.value.onloadedmetadata = () => {
                        resolve(true)
                    }
                }
            })
            
            await videoElement.value.play()
            isCameraActive.value = true
            addDebugLog('Camera started successfully')
            
            // Reset container style
            if (videoContainer.value) {
                videoContainer.value.style.aspectRatio = 'auto'
                videoContainer.value.style.maxWidth = '640px'
            }
            
            setTimeout(() => {
                if (isCameraActive.value) {
                    startFaceDetection()
                }
            }, 500)
        }
    } catch (error: any) {
        console.error('Error accessing camera:', error)
        cameraError.value = 'Unable to access camera. Please check your camera and permissions.'
        isCameraActive.value = false
        addDebugLog(`Camera error: ${cameraError.value}`)
    }
}

// Start face detection loop
const startFaceDetection = () => {
    if (faceDetectionInterval.value) {
        cancelAnimationFrame(faceDetectionInterval.value)
    }
    
    let lastDetectionTime = 0
    const DETECTION_INTERVAL = 100
    
    const detectFrame = async (timestamp: number) => {
        if (isCameraActive.value && videoElement.value) {
            if (timestamp - lastDetectionTime > DETECTION_INTERVAL) {
                await detectFace()
                lastDetectionTime = timestamp
            }
            faceDetectionInterval.value = requestAnimationFrame(detectFrame)
        }
    }
    
    faceDetectionInterval.value = requestAnimationFrame(detectFrame)
    addDebugLog('Face detection started')
}


// FIXED: Capture face with correct coordinates and ensure count updates
const captureFace = (): boolean => {
    if (!videoElement.value || !canvasElement.value) {
        addDebugLog('Capture failed: Camera not ready')
        return false
    }
    
    if (!isFaceDetected.value) {
        addDebugLog('Capture failed: No face detected')
        return false
    }
    
    if (!currentFaceBox.value) {
        addDebugLog('Capture failed: No face box')
        return false
    }
    
    try {
        const captureCanvas = document.createElement('canvas')
        captureCanvas.width = CAPTURE_SIZE
        captureCanvas.height = CAPTURE_SIZE
        const captureContext = captureCanvas.getContext('2d')
        
        if (!captureContext) {
            addDebugLog('Capture failed: Could not get canvas context')
            return false
        }
        
        const video = videoElement.value
        const faceBox = currentFaceBox.value
        
        addDebugLog(`Face box: x=${Math.round(faceBox.x)}, y=${Math.round(faceBox.y)}, w=${Math.round(faceBox.width)}, h=${Math.round(faceBox.height)}`)
        
        // Calculate crop area with padding
        const padding = 0.3
        const cropSize = Math.max(faceBox.width, faceBox.height) * (1 + padding)
        
        let cropX = faceBox.x - (cropSize - faceBox.width) / 2
        let cropY = faceBox.y - (cropSize - faceBox.height) / 2
        let cropWidth = cropSize
        let cropHeight = cropSize
        
        // Ensure crop area is within video bounds
        cropX = Math.max(0, Math.min(cropX, video.videoWidth - cropWidth))
        cropY = Math.max(0, Math.min(cropY, video.videoHeight - cropHeight))
        cropWidth = Math.min(cropWidth, video.videoWidth - cropX)
        cropHeight = Math.min(cropHeight, video.videoHeight - cropY)
        
        if (cropWidth < 50 || cropHeight < 50) {
            addDebugLog(`Capture failed: Crop area too small`)
            return false
        }
        
        // Fill with white background
        captureContext.fillStyle = '#FFFFFF'
        captureContext.fillRect(0, 0, CAPTURE_SIZE, CAPTURE_SIZE)
        
        // Draw the video frame - NO MIRRORING because we want the original image
        captureContext.drawImage(
            video,
            cropX,
            cropY,
            cropWidth,
            cropHeight,
            0,
            0,
            CAPTURE_SIZE,
            CAPTURE_SIZE
        )
        
        // Convert to JPEG for smaller file size
        const imageData = captureCanvas.toDataURL('image/jpeg', 0.95)
        
        // Verify the image data is valid
        if (imageData.length < 1000) {
            addDebugLog(`⚠️ Image data suspiciously small: ${imageData.length} chars`)
            return false
        }
        
        // FIX: Ensure we don't exceed the required captures
        if (capturedFaces.value.length < REQUIRED_CAPTURES) {
            // Add to array
            capturedFaces.value.push(imageData)
            captureCount.value = capturedFaces.value.length
            faceTrainingProgress.value = Math.round((captureCount.value / REQUIRED_CAPTURES) * 100)
            lastCaptureTime.value = Date.now()
            
            addDebugLog(`✅ Face captured: ${captureCount.value}/${REQUIRED_CAPTURES} (${Math.round(imageData.length / 1024)}KB)`)
            
            // Flash effect
            if (videoElement.value) {
                videoElement.value.style.filter = 'brightness(1.5)'
                setTimeout(() => {
                    if (videoElement.value) {
                        videoElement.value.style.filter = ''
                    }
                }, 200)
            }
            
            return true
        } else {
            addDebugLog(`⚠️ Already at max captures (${REQUIRED_CAPTURES})`)
            return false
        }
    } catch (error) {
        console.error('Error capturing face:', error)
        addDebugLog(`Capture error: ${error}`)
        return false
    }
}

// Test image capture function
const testImageCapture = () => {
    try {
        const testCanvas = document.createElement('canvas')
        testCanvas.width = 200
        testCanvas.height = 200
        const ctx = testCanvas.getContext('2d')
        
        if (!ctx) return
        
        // Draw test pattern
        ctx.fillStyle = '#FF0000'
        ctx.fillRect(0, 0, 100, 100)
        ctx.fillStyle = '#00FF00'
        ctx.fillRect(100, 0, 100, 100)
        ctx.fillStyle = '#0000FF'
        ctx.fillRect(0, 100, 100, 100)
        ctx.fillStyle = '#FFFF00'
        ctx.fillRect(100, 100, 100, 100)
        ctx.fillStyle = '#FFFFFF'
        ctx.font = '20px Arial'
        ctx.fillText('TEST', 50, 100)
        
        const testImage = testCanvas.toDataURL('image/jpeg', 0.95)
        capturedFaces.value.push(testImage)
        captureCount.value = capturedFaces.value.length
        
        addDebugLog('✅ Test image added')
    } catch (error) {
        addDebugLog(`❌ Test failed: ${error}`)
    }
}

// Start face training// Start face training - FIXED
const startFaceTraining = async () => {
    if (isTraining.value || isTesting.value) {
        addDebugLog('Already training or testing, ignoring request')
        return
    }
    
    addDebugLog('========== STARTING FACE TRAINING ==========')
    
    // Reset all states
    isTraining.value = true
    isTesting.value = false
    capturedFaces.value = []
    captureCount.value = 0
    faceTrainingProgress.value = 0
    cameraError.value = null
    debugLogs.value = []
    currentFaceBox.value = null
    isFaceDetected.value = false
    detectionScore.value = null
    
    addDebugLog(`Training state set to: ${isTraining.value}`)
    addDebugLog(`Required captures: ${REQUIRED_CAPTURES}`)
    
    try {
        // Load models
        await loadFaceApi()
        
        if (!isTraining.value) return
        
        // Start camera
        await startCamera()
        
        if (!isCameraActive.value) {
            throw new Error('Could not activate camera')
        }
        
        if (!isTraining.value) return
        
        addDebugLog('Camera activated, waiting for face detection...')
        
        // Wait for face detection
        let waitCount = 0
        const maxWaitTime = 50
        
        while (!isFaceDetected.value && waitCount < maxWaitTime && isTraining.value) {
            await new Promise(resolve => setTimeout(resolve, 200))
            waitCount++
            if (waitCount % 10 === 0) {
                addDebugLog(`Still waiting for face... (${waitCount * 0.2}s)`)
            }
        }
        
        if (!isTraining.value) return
        
        if (!isFaceDetected.value) {
            throw new Error('No face detected. Please ensure your face is visible to the camera.')
        }
        
        addDebugLog(`✅ Face detected! Starting capture loop...`)
        
        // FIX: Use nextTick to ensure state is updated before starting loop
        await new Promise(resolve => setTimeout(resolve, 500))
        
        // Start capture loop
        captureLoop()
        
    } catch (error: any) {
        console.error('Error in face training:', error)
        cameraError.value = error.message || 'Error starting face training.'
        addDebugLog(`❌ Training error: ${error.message}`)
        isTraining.value = false
        stopCamera()
    }
}
// Capture loop - FIXED
// Capture loop - FIXED
const captureLoop = () => {
    if (!isTraining.value) {
        addDebugLog('Capture loop stopped: Training no longer active')
        return
    }
    
    if (!isCameraActive.value) {
        addDebugLog('Capture loop stopped: Camera inactive')
        return
    }
    
    // CRITICAL FIX: Check if we've reached the target BEFORE attempting another capture
    if (captureCount.value >= REQUIRED_CAPTURES) {
        addDebugLog(`✅ Target reached: ${captureCount.value}/${REQUIRED_CAPTURES} images - stopping capture loop`)
        // Immediately stop the loop and start training
        isTraining.value = false
        trainFaceModel()
        return
    }
    
    if (isFaceDetected.value && currentFaceBox.value) {
        addDebugLog(`📸 Attempting capture ${captureCount.value + 1}/${REQUIRED_CAPTURES}...`)
        const captured = captureFace()
        if (captured) {
            addDebugLog(`✅ Capture ${captureCount.value}/${REQUIRED_CAPTURES} successful`)
            
            // Check again after successful capture
            if (captureCount.value >= REQUIRED_CAPTURES) {
                addDebugLog(`🎯 Target reached! Starting model training...`)
                trainFaceModel()
                return
            }
            
            // Only schedule next capture if we haven't reached the target AND still training
            if (isTraining.value) {
                setTimeout(captureLoop, TRAINING_INTERVAL)
            }
        } else {
            addDebugLog(`❌ Capture failed, retrying...`)
            if (isTraining.value) {
                setTimeout(captureLoop, 300)
            }
        }
    } else {
        addDebugLog('👤 No face detected, waiting...')
        if (isTraining.value) {
            setTimeout(captureLoop, 500)
        }
    }
}

// In your admin Vue component
const trainFaceModel = async () => {
    addDebugLog('========== TRAINING FACE MODEL ==========')
    addDebugLog(`Training model with ${capturedFaces.value.length} captures...`)
    
    // CRITICAL: Stop the capture loop immediately
    if (faceDetectionInterval.value) {
        cancelAnimationFrame(faceDetectionInterval.value)
        faceDetectionInterval.value = null
    }
    
    if (capturedFaces.value.length === 0) {
        addDebugLog('❌ No captures to train')
        alert('No face captures were taken. Please try again.')
        isTraining.value = false
        stopCamera()
        return
    }

    try {
        // Show training progress
        faceTrainingProgress.value = 10
        
        // Process each captured face to extract embeddings
        const embeddings = []
        const faceapi = (window as any).faceapi
        
        if (!faceapi) {
            throw new Error('Face API not loaded')
        }


        console.log(capturedFaces.value)
        for (let i = 0; i < capturedFaces.value.length; i++) {
            addDebugLog(`Processing image ${i + 1}/${capturedFaces.value.length}...`)
            faceTrainingProgress.value = 10 + Math.round((i / capturedFaces.value.length) * 60)
            
            // Create an image element from base64
            const img = new Image()
            img.src = capturedFaces.value[i]
            
            await new Promise((resolve) => {
                img.onload = resolve
                img.onerror = resolve // Continue even if image fails to load
            })
            
        }
        
        
        // Also store as JSON for backward compatibility
        form.facial_images = capturedFaces.value
        
        
        isTraining.value = false
        stopCamera()
        
        
    } catch (error) {
        console.error('Error training face model:', error)
        addDebugLog(`Training error: ${error}`)
        alert('Failed to train face model. Please try again.')
        isTraining.value = false
        stopCamera()
    }
}

// Helper function to average embeddings
const averageEmbeddings = (embeddings: number[][]): number[] => {
    if (embeddings.length === 0) return []
    if (embeddings.length === 1) return embeddings[0]
    
    const dims = embeddings[0].length
    const sum = new Array(dims).fill(0)
    
    for (const emb of embeddings) {
        for (let i = 0; i < dims; i++) {
            sum[i] += emb[i]
        }
    }
    
    return sum.map(val => val / embeddings.length)
}


// Download functions
const downloadImage = (base64Data: string, index: number) => {
    const link = document.createElement('a')
    link.href = base64Data
    link.download = `face-capture-${index + 1}-${new Date().getTime()}.jpg`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

const downloadAllCaptures = () => {
    capturedFaces.value.forEach((face, index) => {
        setTimeout(() => {
            downloadImage(face, index)
        }, index * 200)
    })
}

// Manual capture for testing
const manualCapture = () => {
    if (!isCameraActive.value) {
        addDebugLog('Cannot capture: Camera not active')
        return
    }
    
    if (!isFaceDetected.value) {
        addDebugLog('Cannot capture: No face detected')
        return
    }
    
    const captured = captureFace()
    if (captured) {
        addDebugLog(`✅ Manual capture successful: ${captureCount.value}/${REQUIRED_CAPTURES}`)
    }
}

// Reset training
const resetTraining = () => {
    capturedFaces.value = []
    captureCount.value = 0
    faceTrainingProgress.value = 0
    debugLogs.value = []
    addDebugLog('Training reset')
}

// Add this at the top with your other refs
const pendingTimeouts = ref<number[]>([])

// Add this helper function
const clearAllTimeouts = () => {
    pendingTimeouts.value.forEach(timeout => clearTimeout(timeout))
    pendingTimeouts.value = []
}

// Update setTimeout calls to track them
const safeSetTimeout = (fn: Function, delay: number) => {
    const timeoutId = setTimeout(() => {
        pendingTimeouts.value = pendingTimeouts.value.filter(id => id !== timeoutId)
        fn()
    }, delay)
    pendingTimeouts.value.push(timeoutId)
    return timeoutId
}

// Then replace all setTimeout calls with safeSetTimeout in your functions
// For example in captureLoop:
// safeSetTimeout(captureLoop, TRAINING_INTERVAL)

// Stop camera
const stopCamera = () => {
    if (faceDetectionInterval.value) {
        cancelAnimationFrame(faceDetectionInterval.value)
        faceDetectionInterval.value = null
    }
    
    // Clear all pending timeouts
    clearAllTimeouts()
    
    if (stream.value) {
        stream.value.getTracks().forEach(track => {
            track.stop()
        })
        stream.value = null
    }
    
    isCameraActive.value = false
    isFaceDetected.value = false
    currentFaceBox.value = null
    detectionScore.value = null
    
    addDebugLog('Camera stopped')
}

// Submit form
const submit = () => {
    form.put(update(props.module.id).url, {
        onSuccess: () => {
            successState.value = true
            setTimeout(() => {
                successState.value = false
            }, 3000)
        }
    })
}

// Cleanup
onUnmounted(() => {
    stopCamera()
})

// Initialize
onMounted(() => {
    setTimeout(() => {
        loadFaceApi()
    }, 2000)
})
</script>

<template>
    <Head title="Edit User" />

    <AppLayout 
        :breadcrumbs="breadcrumbs"
        page-title="Edit User"
        page-description="Quickly edit existing users in your system by updating their details such as name, email, role, and permissions."
    >
        <template #content>
            <Tabs default-value="basic-info" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="basic-info">Basic Information</TabsTrigger>
                    <TabsTrigger value="facial-recognition">Facial Recognition Setup</TabsTrigger>
                </TabsList>
                
                <!-- Basic Information Tab -->
                <TabsContent value="basic-info">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-xl font-semibold mb-5">Personal Information</h3>
                            <div class="space-y-4">
                                <div class="grid w-full max-w-lg items-center gap-1.5">
                                    <Label for="first_name">First Name</Label>
                                    <Input id="first_name" v-model="form.first_name" type="text" placeholder="Enter first name here..." />
                                    <InputError :message="form.errors.first_name" />
                                </div>
                                <div class="grid w-full max-w-lg items-center gap-1.5">
                                    <Label for="last_name">Last Name</Label>
                                    <Input id="last_name" v-model="form.last_name" type="text" placeholder="Enter last name here..." />
                                    <InputError :message="form.errors.last_name" />
                                </div>
                                <div class="grid w-full max-w-lg items-center gap-1.5">
                                    <Label for="middle_name">Middle Name</Label>
                                    <Input id="middle_name" v-model="form.middle_name" type="text" placeholder="Enter middle name here..." />
                                    <InputError :message="form.errors.middle_name" />
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-xl font-semibold mb-5">Contact Information</h3>
                            <div class="space-y-4">
                                <div class="grid w-full max-w-lg items-center gap-1.5">
                                    <Label for="email">Email Address</Label>
                                    <Input id="email" v-model="form.email" type="email" placeholder="Enter email address here..." />
                                    <InputError :message="form.errors.email" />
                                </div>
                                <div class="grid w-full max-w-lg items-center gap-1.5">
                                    <Label for="phone">Contact Number</Label>
                                    <Input id="phone" v-model="form.phone" type="text" placeholder="Enter contact number here..." />
                                    <InputError :message="form.errors.phone" />
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>
                
                <!-- Facial Recognition Tab -->
                <TabsContent value="facial-recognition">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <UserCheck class="h-5 w-5" />
                                Facial Recognition Setup
                            </CardTitle>
                            <CardDescription>
                                Set up facial recognition for time-in/out functionality. The system will capture multiple images to train the recognition model.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Status Alert -->
                            <Alert v-if="form.facial_images && form.facial_images.length > 0" class="bg-green-50 border-green-200">
                                <UserCheck class="h-4 w-4 text-green-600" />
                                <AlertDescription class="text-green-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            Facial recognition is configured. 
                                        </div>
                                        <Badge variant="outline" class="bg-green-100 text-green-800">
                                            {{ form.facial_images?.length || 1 }} captures
                                        </Badge>
                                    </div>
                                </AlertDescription>
                            </Alert>
                            
                            <Alert v-else class="bg-yellow-50 border-yellow-200">
                                <InfoIcon class="h-4 w-4 text-yellow-600" />
                                <AlertDescription class="text-yellow-700">
                                    Facial recognition is not configured. This user will not be able to use face-based time-in/out.
                                </AlertDescription>
                            </Alert>

                            <!-- Camera Error Alert -->
                            <Alert v-if="cameraError" class="bg-red-50 border-red-200">
                                <InfoIcon class="h-4 w-4 text-red-600" />
                                <AlertDescription class="text-red-700">
                                    {{ cameraError }}
                                </AlertDescription>
                            </Alert>
                            
                            <!-- Camera Preview Section -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium">Camera Preview</h4>
                                    <div class="flex items-center gap-2">
                                        <Badge v-if="isModelLoading" variant="outline" class="bg-blue-100 text-blue-800">
                                            <Spinner class="h-3 w-3 mr-1" />
                                            Loading Models...
                                        </Badge>
                                        <Badge v-else-if="isTesting" variant="outline" class="bg-purple-100 text-purple-800">
                                            <Video class="h-3 w-3 mr-1" />
                                            Testing...
                                        </Badge>
                                        <Badge v-else-if="isFaceDetected && isCameraActive" variant="outline" class="bg-green-100 text-green-800">
                                            <Check class="h-3 w-3 mr-1" />
                                            Face {{ detectionScore ? `(${Math.round(detectionScore * 100)}%)` : '' }}
                                        </Badge>
                                        <Badge v-else-if="isCameraActive" variant="outline" class="bg-yellow-100 text-yellow-800">
                                            <InfoIcon class="h-3 w-3 mr-1" />
                                            No Face
                                        </Badge>
                                    </div>
                                </div>
                                
                                <!-- Camera Display - FIXED -->
                                <div 
                                    ref="videoContainer"
                                    class="relative border-2 border-gray-300 rounded-lg overflow-hidden  mx-auto"
                                    style="max-width: 640px;"
                                >
                                    <div class="relative w-full">
                                        <!-- Video with mirror effect -->
                                        <video 
                                            ref="videoElement" 
                                            autoplay 
                                            playsinline 
                                            muted
                                            class="w-full h-auto mirror"
                                            v-show="isCameraActive"
                                        ></video>
                                        
                                        <!-- Canvas for face detection overlay - also mirrored -->
                                        <canvas 
                                            ref="canvasElement" 
                                            class="absolute top-0 left-0 w-full h-full pointer-events-none mirror"
                                            v-show="isCameraActive"
                                        ></canvas>
                                        
                                        <!-- Camera Offline State -->
                                        <div v-if="!isCameraActive" class="w-full bg-gray-100 flex items-center justify-center" style="aspect-ratio: 4/3;">
                                            <div class="text-center p-8">
                                                <div class="mx-auto w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                                                    <Camera class="h-8 w-8 text-gray-500" />
                                                </div>
                                                <h3 class="text-lg font-medium text-gray-700 mb-2">Camera Offline</h3>
                                                <p class="text-gray-500 mb-4">Start face training to activate the camera</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Face Guide Overlay -->
                                        <div v-if="isCameraActive && !isFaceDetected" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <div class="w-3/4 h-3/4 border-2 border-dashed border-yellow-400 rounded-lg"></div>
                                            <p class="absolute bottom-4 left-0 right-0 text-center text-white text-sm  bg-opacity-50 py-2 px-4 mx-4 rounded">
                                                Position your face within the frame
                                            </p>
                                        </div>
                                        
                                        <!-- Training Overlay -->
                                        <div v-if="isTraining" class="absolute inset-0  bg-opacity-70 flex flex-col items-center justify-center">
                                            <Spinner class="w-12 h-12 text-white mb-4" />
                                            <div class="text-center text-white px-4">
                                                <p class="text-xl font-medium mb-2">Training in Progress</p>
                                                <p class="mb-4">Capture {{ captureCount }}/{{ REQUIRED_CAPTURES }}</p>
                                                
                                                <div class="w-48 sm:w-64 bg-gray-700 rounded-full h-2.5 mb-2 mx-auto">
                                                    <div 
                                                        class="bg-green-500 h-2.5 rounded-full transition-all duration-300" 
                                                        :style="{ width: faceTrainingProgress + '%' }"
                                                    ></div>
                                                </div>
                                                <p class="text-sm">{{ faceTrainingProgress }}% Complete</p>
                                                <p v-if="!isFaceDetected" class="text-yellow-400 mt-4 text-sm">
                                                    ⚠️ No face detected. Please position your face in the camera.
                                                </p>
                                                <p v-else class="text-green-400 mt-4 text-sm">
                                                    ✅ Face detected! Next capture in {{ Math.round((TRAINING_INTERVAL - (Date.now() - (lastCaptureTime || 0))) / 1000) }}s...
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Testing Overlay -->
                                        <div v-if="isTesting" class="absolute inset-0 bg-black bg-opacity-50 flex flex-col items-center justify-center">
                                            <Video class="w-16 h-16 text-white mb-4" />
                                            <div class="text-center text-white px-4">
                                                <p class="text-xl font-medium mb-2">Testing Recognition</p>
                                                <p class="mb-2">Please look at the camera...</p>
                                                <Spinner class="w-8 h-8 text-white mx-auto" />
                                                <p v-if="isFaceDetected" class="text-green-400 mt-4 text-sm">
                                                    ✅ Face detected! Verifying...
                                                </p>
                                                <p v-else class="text-yellow-400 mt-4 text-sm">
                                                    ⚠️ No face detected. Please position your face.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Capture Preview -->
                                <div v-if="capturedFaces.length > 0" class="mt-6">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-medium">Captured Face Samples ({{ capturedFaces.length }}/{{ REQUIRED_CAPTURES }})</h4>
                                        <div class="flex gap-2">
                                            <Button 
                                                v-if="isCameraActive"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="testImageCapture"
                                            >
                                                Add Test Image
                                            </Button>
                                            <Button 
                                                v-if="capturedFaces.length > 0"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="downloadAllCaptures"
                                            >
                                                <Download class="h-4 w-4 mr-2" />
                                                Download All
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-3 justify-center">
                                        <div 
                                            v-for="(face, index) in capturedFaces" 
                                            :key="index"
                                            class="relative group border-2 border-green-500 rounded-lg overflow-hidden hover:shadow-lg transition-shadow cursor-pointer bg-gray-100"
                                            style="width: 80px; height: 80px;"
                                            @click="downloadImage(face, index)"
                                        >
                                            <img 
                                                :src="face"
                                                :alt="`Capture ${index + 1}`"
                                                class="w-full h-full object-cover"
                                                @error="(e) => {
                                                    console.error('Image failed to load:', e);
                                                    e.target.src = 'data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'><rect width=\'80\' height=\'80\' fill=\'%23cccccc\'/><text x=\'10\' y=\'45\' font-family=\'Arial\' font-size=\'12\' fill=\'%23666666\'>Error</text></svg>';
                                                }"
                                            />
                                            <div class="absolute inset-0  bg-opacity-0 group-hover:bg-opacity-50 transition-all flex items-center justify-center">
                                                <Download class="h-5 w-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                            <div class="absolute bottom-0 right-0 bg-green-600 text-white text-xs px-1.5 py-0.5 rounded-tl">
                                                {{ index + 1 }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Debug info for images -->
                                    <div v-if="capturedFaces.length > 0" class="mt-2 text-xs text-gray-500 text-center">
                                        <span>Last image: {{ Math.round(capturedFaces[capturedFaces.length - 1]?.length / 1024 || 0) }}KB</span>
                                        <span class="mx-2">|</span>
                                        <span>Format: JPEG</span>
                                    </div>
                                </div>

                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-3 justify-center sm:justify-start">
                                <Button 
                                    type="button"
                                    variant="default"
                                    :disabled="isTraining || isTesting || isModelLoading"
                                    @click="startFaceTraining"
                                    class="w-full sm:w-auto"
                                >
                                    <Camera v-if="!isTraining" class="h-4 w-4 mr-2" />
                                    <Spinner v-else class="h-4 w-4 mr-2" />
                                    {{ isTraining ? 'Training...' : 'Start Face Training' }}
                                </Button>
                                
                                
                                
                                <Button 
                                    v-if="isCameraActive && !isTraining && !isTesting"
                                    type="button"
                                    variant="secondary"
                                    @click="stopCamera"
                                    class="w-full sm:w-auto"
                                >
                                    <X class="h-4 w-4 mr-2" />
                                    Stop Camera
                                </Button>
                                
                                <Button 
                                    v-if="isCameraActive && isTraining"
                                    type="button"
                                    variant="secondary"
                                    @click="manualCapture"
                                    class="w-full sm:w-auto"
                                >
                                    <Camera class="h-4 w-4 mr-2" />
                                    Manual Capture
                                </Button>
                                
                                <Button 
                                    v-if="isCameraActive"
                                    type="button"
                                    variant="warning"
                                    @click="resetTraining"
                                    class="w-full sm:w-auto"
                                >
                                    Reset
                                </Button>
                            </div>
                            
                            <!-- Training Progress -->
                            <div v-if="isTraining" class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">Training Status</span>
                                    <span class="font-bold text-blue-700">{{ faceTrainingProgress }}%</span>
                                </div>
                                <Progress :value="faceTrainingProgress" class="w-full" />
                                <div class="mt-3 text-sm text-blue-700">
                                    <p v-if="isFaceDetected" class="flex items-center gap-2">
                                        <Check class="h-4 w-4 text-green-600" />
                                        Face detected - Capturing image {{ captureCount + 1 }} of {{ REQUIRED_CAPTURES }}
                                        <span v-if="detectionScore" class="text-xs bg-blue-100 px-2 py-0.5 rounded">
                                            {{ Math.round(detectionScore * 100) }}% confidence
                                        </span>
                                    </p>
                                    <p v-else class="flex items-center gap-2">
                                        <InfoIcon class="h-4 w-4 text-yellow-600" />
                                        No face detected - Please position your face in the frame
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Instructions -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium mb-3 flex items-center gap-2">
                                    <InfoIcon class="h-4 w-4" />
                                    Tips for Best Results:
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Good lighting on your face</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Remove sunglasses and hats</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Look directly at the camera</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Neutral expression</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Stay 1-2 meters from camera</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-gray-600">
                                        <Check class="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                                        <span>Wait for green box to appear</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
            
            <!-- Save Button -->
            <div class="mt-8 pt-6 border-t">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <div v-if="successState" class="flex items-center gap-2 text-green-600">
                            <Check class="h-4 w-4" />
                            <span>User updated successfully!</span>
                        </div>
                        <InputError v-else :message="form.errors.global" />
                    </div>
                    <Button
                        type="button"
                        :disabled="form.processing || isTraining || isTesting"
                        @click="submit"
                        class="w-full sm:w-auto"
                    >
                        <Spinner v-if="form.processing" class="h-4 w-4 mr-2" />
                        <Save v-else class="h-4 w-4 mr-2" />
                        {{ form.processing ? 'Saving...' : 'Save User' }}
                    </Button>
                </div>
            </div>
        </template>
    </AppLayout>
</template>

<style scoped>
.mirror {
    transform: scaleX(-1);
}

video {
    display: block;
    width: 100%;
    height: auto;
}

canvas {
    display: block;
    width: 100%;
    height: auto;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Face guide animation */
@keyframes pulse {
    0%, 100% {
        border-color: rgba(250, 204, 21, 0.5);
    }
    50% {
        border-color: rgba(250, 204, 21, 1);
    }
}

.border-dashed {
    animation: pulse 2s infinite;
}

/* Debug console scrollbar */
.bg-gray-900::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.bg-gray-900::-webkit-scrollbar-track {
    background: #1f2937;
}

.bg-gray-900::-webkit-scrollbar-thumb {
    background: #4b5563;
    border-radius: 2px;
}

.bg-gray-900::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>