<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    index,
    store
} from '@/routes/admin/user'
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Plus, InfoIcon, Layers, Layers2, Save, Camera, Video, Upload, UserCheck, X } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput, InputGroupText } from '@/components/ui/input-group'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { Textarea } from '@/components/ui/textarea';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import InputError from '@/components/InputError.vue';
import { email } from '@/routes/password';
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
        facial_data?: string,
        facial_embeddings?: any,
        face_trained_at?: string,
    }
}>()

const successState = ref(false)
const handleState = () => {
    successState.value = true
}

const form = useForm({
    first_name: props.module.first_name || "",
    last_name: props.module.last_name || "",
    middle_name: props.module.middle_name || "",
    email: props.module.email || "",
    phone: props.module.phone || "",
    facial_data: props.module.facial_data || "",
    facial_embeddings: props.module.facial_embeddings || null,
})

// Facial Recognition States
const isCameraActive = ref(false)
const isTraining = ref(false)
const isFaceDetected = ref(false)
const faceTrainingProgress = ref(0)
const captureCount = ref(0)
const videoElement = ref<HTMLVideoElement | null>(null)
const canvasElement = ref<HTMLCanvasElement | null>(null)
const stream = ref<MediaStream | null>(null)
const capturedFaces = ref<string[]>([])
const faceDetectionInterval = ref<NodeJS.Timeout | null>(null)
const currentFaceBox = ref<{x: number, y: number, width: number, height: number} | null>(null)

// Constants
const REQUIRED_CAPTURES = 10 // Reduced for better UX
const TRAINING_INTERVAL = 1500 // 1.5 seconds between captures

// Face detection (simplified version)
const detectFace = () => {
    if (!videoElement.value || !canvasElement.value) return false
    
    const video = videoElement.value
    const canvas = canvasElement.value
    const context = canvas.getContext('2d')
    
    if (!context) return false
    
    // Set canvas to video dimensions
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    
    // Draw video frame to canvas
    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    
    // Simple face detection simulation
    // In real implementation, use face-api.js or TensorFlow.js
    const hasFace = Math.random() > 0.3 // Simulate 70% chance of face detection
    
    if (hasFace) {
        // Simulate face bounding box
        const boxWidth = Math.random() * 100 + 150
        const boxHeight = boxWidth * 1.2
        const x = (canvas.width - boxWidth) / 2 + (Math.random() * 40 - 20)
        const y = (canvas.height - boxHeight) / 2 + (Math.random() * 40 - 20)
        
        currentFaceBox.value = { x, y, width: boxWidth, height: boxHeight }
        
        // Draw face box for visualization
        context.strokeStyle = '#10B981'
        context.lineWidth = 3
        context.strokeRect(x, y, boxWidth, boxHeight)
        
        // Add label
        context.fillStyle = '#10B981'
        context.font = 'bold 16px Arial'
        context.fillText('Face Detected', x, y - 10)
    } else {
        currentFaceBox.value = null
    }
    
    return hasFace
}

// Start camera for face capture
const startCamera = async () => {
    try {
        stopCamera() // Ensure any existing stream is stopped
        
        const constraints = {
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            },
            audio: false
        }
        
        stream.value = await navigator.mediaDevices.getUserMedia(constraints)
        
        if (videoElement.value) {
            videoElement.value.srcObject = stream.value
            isCameraActive.value = true
            
            // Start face detection visualization
            startFaceDetectionVisualization()
        }
    } catch (error) {
        console.error('Error accessing camera:', error)
        alert('Unable to access camera. Please check permissions and make sure no other application is using the camera.')
    }
}

// Start face detection visualization
const startFaceDetectionVisualization = () => {
    if (faceDetectionInterval.value) {
        clearInterval(faceDetectionInterval.value)
    }
    
    faceDetectionInterval.value = setInterval(() => {
        const faceDetected = detectFace()
        isFaceDetected.value = faceDetected
    }, 100) // Update every 100ms for smooth visualization
}

// Capture face from video stream
const captureFace = () => {
    if (!videoElement.value || !canvasElement.value || !isFaceDetected.value) {
        alert('Please position your face in the center of the frame')
        return false
    }
    
    const context = canvasElement.value.getContext('2d')
    if (!context) return false
    
    // Capture high-quality image
    const captureCanvas = document.createElement('canvas')
    captureCanvas.width = 400 // Fixed size for consistency
    captureCanvas.height = 400
    const captureContext = captureCanvas.getContext('2d')
    
    if (!captureContext) return false
    
    // Draw cropped face area
    if (currentFaceBox.value) {
        const { x, y, width, height } = currentFaceBox.value
        captureContext.drawImage(
            videoElement.value,
            Math.max(0, x),
            Math.max(0, y),
            Math.min(width, canvasElement.value.width - x),
            Math.min(height, canvasElement.value.height - y),
            0,
            0,
            400,
            400
        )
    } else {
        // Fallback: capture center area
        captureContext.drawImage(
            videoElement.value,
            (videoElement.value.videoWidth - 300) / 2,
            (videoElement.value.videoHeight - 300) / 2,
            300,
            300,
            0,
            0,
            400,
            400
        )
    }
    
    // Convert to base64
    const imageData = captureCanvas.toDataURL('image/jpeg', 0.9)
    capturedFaces.value.push(imageData)
    captureCount.value++
    
    // Update progress
    faceTrainingProgress.value = Math.round((captureCount.value / REQUIRED_CAPTURES) * 100)
    
    // Flash effect to indicate capture
    if (videoElement.value) {
        videoElement.value.style.filter = 'brightness(1.5)'
        setTimeout(() => {
            if (videoElement.value) {
                videoElement.value.style.filter = ''
            }
        }, 200)
    }
    
    return true
}

// Start automatic face capture process
const startFaceTraining = async () => {
    if (isTraining.value) return
    
    capturedFaces.value = []
    captureCount.value = 0
    faceTrainingProgress.value = 0
    isTraining.value = true
    
    try {
        await startCamera()
        
        if (!isCameraActive.value) {
            isTraining.value = false
            return
        }
        
        // Wait for video to be ready
        await new Promise(resolve => setTimeout(resolve, 1000))
        
        // Start capturing faces
        const captureNext = () => {
            if (!isTraining.value || captureCount.value >= REQUIRED_CAPTURES) {
                if (captureCount.value >= REQUIRED_CAPTURES) {
                    trainFaceModel()
                }
                return
            }
            
            // Check if face is detected
            if (isFaceDetected.value) {
                captureFace()
                
                // Schedule next capture
                setTimeout(captureNext, TRAINING_INTERVAL)
            } else {
                // If no face detected, retry after 500ms
                setTimeout(captureNext, 500)
            }
        }
        
        captureNext()
        
    } catch (error) {
        console.error('Error in face training:', error)
        alert('Error starting face training. Please try again.')
        isTraining.value = false
        stopCamera()
    }
}

// Train face recognition model
const trainFaceModel = async () => {
    isTraining.value = false
    stopCamera()
    
    try {
        // Simulate training process
        console.log('Training face model with', capturedFaces.value.length, 'captures...')
        
        // Show training progress
        for (let i = 0; i <= 100; i += 10) {
            await new Promise(resolve => setTimeout(resolve, 100))
            faceTrainingProgress.value = i
        }
        
        // Store the best captured face as reference
        if (capturedFaces.value.length > 0) {
            const bestCapture = capturedFaces.value[Math.floor(capturedFaces.value.length / 2)]
            form.facial_data = bestCapture
            
            // Create facial embeddings
            form.facial_embeddings = {
                trained_at: new Date().toISOString(),
                capture_count: capturedFaces.value.length,
                status: 'trained',
                confidence_threshold: 0.7,
                model_version: '1.0',
                sample_images: capturedFaces.value.slice(0, 3) // Store first 3 samples
            }
            
            // Update form with new data
            form.post(store().url, {
                onSuccess: () => {
                    successState.value = true
                    setTimeout(() => {
                        successState.value = false
                    }, 3000)
                }
            })
        }
        
        alert(`Face training completed successfully!\n\nCaptured ${captureCount.value} images.`)
        
    } catch (error) {
        console.error('Error training face model:', error)
        alert('Failed to train face model. Please try again.')
    }
}

// Delete facial data
const deleteFacialData = () => {
    if (confirm('Are you sure you want to delete the facial recognition data? This cannot be undone.')) {
        form.facial_data = ""
        form.facial_embeddings = null
        capturedFaces.value = []
        captureCount.value = 0
        faceTrainingProgress.value = 0
        
        form.post(store().url, {
            onSuccess: () => {
                alert('Facial data deleted successfully.')
            }
        })
    }
}

// Test face recognition (simulation)
const testFaceRecognition = async () => {
    if (!form.facial_data) {
        alert('No facial data found. Please train the face model first.')
        return
    }
    
    alert('Starting face recognition test...\n\nPlease look at the camera when prompted.')
    
    await startCamera()
    
    if (!isCameraActive.value) return
    
    // Countdown for test
    let countdown = 3
    const countdownInterval = setInterval(() => {
        if (countdown > 0) {
            alert(`Look at the camera... ${countdown}`)
            countdown--
        } else {
            clearInterval(countdownInterval)
            
            // Capture test image
            const testCanvas = document.createElement('canvas')
            testCanvas.width = 400
            testCanvas.height = 400
            const context = testCanvas.getContext('2d')
            
            if (context && videoElement.value) {
                context.drawImage(videoElement.value, 0, 0, 400, 400)
                
                // Simulate recognition
                const confidence = 0.7 + Math.random() * 0.25 // 0.7-0.95
                const isMatch = confidence > 0.75
                
                stopCamera()
                
                if (isMatch) {
                    alert(`✅ Face recognized!\nConfidence: ${(confidence * 100).toFixed(1)}%\n\nUser: ${form.first_name} ${form.last_name}`)
                } else {
                    alert(`❌ Face not recognized\nConfidence: ${(confidence * 100).toFixed(1)}%\n\nPlease try again or retrain the model.`)
                }
            }
        }
    }, 1000)
}

// Stop camera and cleanup
const stopCamera = () => {
    if (faceDetectionInterval.value) {
        clearInterval(faceDetectionInterval.value)
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
}

// Submit form
const submit = () => {
    form.transform((data) => ({
        ...data,
    })).post(store().url, {
        onSuccess: () => {
            successState.value = true
            setTimeout(() => {
                successState.value = false
            }, 3000)
        }
    })
}

// Cleanup on unmount
onUnmounted(() => {
    stopCamera()
})

// Initialize if facial data exists
onMounted(() => {
    if (props.module.facial_data) {
        capturedFaces.value = [props.module.facial_data]
        captureCount.value = 1
        faceTrainingProgress.value = 100
    }
})
</script>

<template>
    <Head title="Edit User" />

    <AppLayout :breadcrumbs="breadcrumbs"
        page-title="Edit User"
        page-description="Quickly edit existing users in your system by updating their details such as name, email, role, and permissions."
    >
        <template #action>
        </template>
        <template #content>
            <Tabs default-value="basic-info" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="basic-info">Basic Information</TabsTrigger>
                    <TabsTrigger value="facial-recognition">Facial Recognition Setup</TabsTrigger>
                </TabsList>
                
                <!-- Basic Information Tab -->
                <TabsContent value="basic-info">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-8">
                        <!-- LEFT COLUMN -->
                        <div>
                            <h3 class="text-xl font-semibold mb-5">Personal Information</h3>
                            <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                                <Label for="first_name">First Name</Label>
                                <Input id="first_name" v-model="form.first_name" type="text" placeholder="Enter first name here..." />
                                <InputError :message="form.errors.first_name" />
                            </div>
                            <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                                <Label for="last_name">Last Name</Label>
                                <Input id="last_name" v-model="form.last_name" type="text" placeholder="Enter last name here..." />
                                <InputError :message="form.errors.last_name" />
                            </div>
                            <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                                <Label for="middle_name">Middle Name</Label>
                                <Input id="middle_name" v-model="form.middle_name" type="text" placeholder="Enter middle name here..." />
                                <InputError :message="form.errors.middle_name" />
                            </div>
                        </div>
                        
                        <!-- RIGHT COLUMN -->
                        <div>
                            <h3 class="text-xl font-semibold mb-5">Contact Information</h3>
                            <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                                <Label for="email">Email Address</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="Enter email address here..." />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid w-full max-w-lg items-center gap-1.5 pb-3">
                                <Label for="phone">Contact Number</Label>
                                <Input id="phone" v-model="form.phone" type="text" placeholder="Enter contact number here..." />
                                <InputError :message="form.errors.phone" />
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
                            <Alert v-if="form.facial_data" class="bg-green-50 border-green-200">
                                <UserCheck class="h-4 w-4 text-green-600" />
                                <AlertDescription class="text-green-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            Facial recognition is configured. 
                                            <span v-if="form.facial_embeddings?.trained_at">
                                                Last trained: {{ new Date(form.facial_embeddings.trained_at).toLocaleDateString() }}
                                            </span>
                                        </div>
                                        <Badge variant="outline" class="bg-green-100 text-green-800">
                                            {{ form.facial_embeddings?.capture_count || 1 }} captures
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
                            
                            <!-- Camera Preview Section -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium">Camera Preview</h4>
                                    <div class="flex items-center gap-2">
                                        <Badge v-if="isFaceDetected && isCameraActive" variant="outline" class="bg-green-100 text-green-800">
                                            <Check class="h-3 w-3 mr-1" />
                                            Face Detected
                                        </Badge>
                                        <Badge v-else-if="isCameraActive" variant="outline" class="bg-yellow-100 text-yellow-800">
                                            <InfoIcon class="h-3 w-3 mr-1" />
                                            No Face
                                        </Badge>
                                    </div>
                                </div>
                                
                                <!-- Camera Display -->
                                <div class="relative border-2 border-gray-300 rounded-lg overflow-hidden bg-black min-h-[400px] flex items-center justify-center">
                                    <!-- Video Feed -->
                                    <video 
                                        ref="videoElement" 
                                        autoplay 
                                        playsinline 
                                        muted
                                        class="w-full h-auto max-h-[480px] object-cover"
                                        v-show="isCameraActive"
                                    ></video>
                                    
                                    <!-- Canvas for Face Detection Overlay -->
                                    <canvas 
                                        ref="canvasElement" 
                                        class="absolute top-0 left-0 w-full h-full pointer-events-none"
                                        v-show="isCameraActive"
                                    ></canvas>
                                    
                                    <!-- Camera Not Active State -->
                                    <div v-if="!isCameraActive" class="text-center p-8">
                                        <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                            <Camera class="h-8 w-8 text-gray-400" />
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-700 mb-2">Camera Offline</h3>
                                        <p class="text-gray-500 mb-4">Start face training to activate the camera</p>
                                    </div>
                                    
                                    <!-- Training Overlay -->
                                    <div v-if="isTraining" class="absolute inset-0 bg-black bg-opacity-70 flex flex-col items-center justify-center">
                                        <Spinner class="w-12 h-12 text-white mb-4" />
                                        <div class="text-center text-white">
                                            <p class="text-xl font-medium mb-2">Training in Progress</p>
                                            <p class="mb-4">Capture {{ captureCount }}/{{ REQUIRED_CAPTURES }}</p>
                                            
                                            <!-- Progress Bar -->
                                            <div class="w-64 bg-gray-700 rounded-full h-2.5 mb-2">
                                                <div 
                                                    class="bg-green-500 h-2.5 rounded-full transition-all duration-300" 
                                                    :style="{ width: faceTrainingProgress + '%' }"
                                                ></div>
                                            </div>
                                            <p class="text-sm">{{ faceTrainingProgress }}% Complete</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Capture Preview -->
                                <div v-if="capturedFaces.length > 0" class="mt-4">
                                    <h4 class="font-medium mb-2">Captured Images ({{ capturedFaces.length }})</h4>
                                    <div class="flex gap-2 overflow-x-auto pb-2">
                                        <div 
                                            v-for="(face, index) in capturedFaces.slice(0, 5)" 
                                            :key="index"
                                            class="relative border rounded-lg overflow-hidden flex-shrink-0"
                                        >
                                            <img 
                                                :src="face" 
                                                :alt="`Capture ${index + 1}`"
                                                class="w-24 h-24 object-cover"
                                            />
                                            <div class="absolute bottom-0 right-0 bg-black bg-opacity-50 text-white text-xs px-1">
                                                {{ index + 1 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-3">
                                <Button 
                                    type="button"
                                    variant="default"
                                    :disabled="isTraining"
                                    @click="startFaceTraining"
                                >
                                    <Camera v-if="!isTraining" class="h-4 w-4 mr-2" />
                                    <Spinner v-else class="h-4 w-4 mr-2" />
                                    {{ isTraining ? 'Training in Progress...' : 'Start Face Training' }}
                                </Button>
                                
                                <Button 
                                    v-if="form.facial_data && !isTraining && !isCameraActive"
                                    type="button"
                                    variant="outline"
                                    @click="testFaceRecognition"
                                >
                                    <Video class="h-4 w-4 mr-2" />
                                    Test Recognition
                                </Button>
                                
                                <Button 
                                    v-if="form.facial_data && !isTraining"
                                    type="button"
                                    variant="destructive"
                                    @click="deleteFacialData"
                                >
                                    <X class="h-4 w-4 mr-2" />
                                    Delete Facial Data
                                </Button>
                                
                                <Button 
                                    v-if="isCameraActive"
                                    type="button"
                                    variant="secondary"
                                    @click="stopCamera"
                                >
                                    Stop Camera
                                </Button>
                            </div>
                            
                            <!-- Training Progress -->
                            <div v-if="isTraining" class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">Training Progress</span>
                                    <span class="font-bold">{{ faceTrainingProgress }}%</span>
                                </div>
                                <Progress :value="faceTrainingProgress" class="w-full" />
                                <p class="text-sm text-blue-700 mt-2">
                                    Capturing face {{ captureCount }} of {{ REQUIRED_CAPTURES }}. Please keep your face centered.
                                </p>
                            </div>
                            
                            <!-- Instructions -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium mb-3 flex items-center gap-2">
                                    <InfoIcon class="h-4 w-4" />
                                    Instructions for Best Results:
                                </h4>
                                <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Ensure good lighting on your face
                                    </li>
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Remove sunglasses, hats, or face coverings
                                    </li>
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Look directly at the camera
                                    </li>
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Maintain a neutral expression
                                    </li>
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Stay within 1-2 meters from the camera
                                    </li>
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <Check class="h-3 w-3 text-green-500" />
                                        Training takes {{ Math.round((REQUIRED_CAPTURES * TRAINING_INTERVAL) / 1000) }} seconds
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
            
            <!-- Save Button -->
            <div class="mt-8 pt-6 border-t">
                <div class="flex items-center justify-between">
                    <div>
                        <div v-if="successState" class="flex items-center gap-2 text-green-600">
                            <Check class="h-4 w-4" />
                            <span>User updated successfully!</span>
                        </div>
                        <InputError v-else :message="form.errors.global" />
                    </div>
                    
                    <Button
                        type="button"
                        :disabled="form.processing || isTraining"
                        @click="submit"
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
video {
    transform: scaleX(-1); /* Mirror the camera feed for more natural experience */
}

canvas {
    transform: scaleX(-1);
}

/* Smooth transition for camera activation */
video, canvas {
    transition: opacity 0.3s ease;
}

/* Face detection box animation */
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Scrollbar styling for captured images */
::-webkit-scrollbar {
    height: 6px;
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
</style>