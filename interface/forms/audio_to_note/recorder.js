document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded and parsed");

    const startRecordingButton = document.getElementById('start-recording');
    const stopRecordingButton = document.getElementById('stop-recording');
    const audioPlayback = document.getElementById('audio_playback');
    const uploadButton = document.getElementById('upload-button');
    const audioFileInput = document.getElementById('audio_file');
    const audioForm = document.querySelector('form[name="audio_to_note_form"]');

    console.log("Script start, trying to find buttons...");
    console.log("Start button found:", startRecordingButton);
    console.log("Stop button found:", stopRecordingButton);
    console.log("Audio form found:", audioForm);

    let mediaRecorder;
    let audioChunks = [];
    let audioBlob;
    let audioFile;

    async function getMicrophone() {
        try {
            console.log("Requesting microphone access...");
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            console.log("Microphone access granted.");
            return stream;
        } catch (error) {
            console.error("Error accessing microphone:", error);
            alert("Error accessing microphone. Please ensure you have given permission and that the site is served over HTTPS.");
            return null;
        }
    }

    async function startRecording() {
        console.log("'startRecording' function called.");
        const stream = await getMicrophone();
        if (!stream) {
            console.error("Could not get microphone stream. Aborting recording.");
            return;
        }

        const options = {
            // You can change the MIME type to control the recording format.
            // Common options include: 'audio/webm', 'audio/ogg', 'audio/wav'.
            // 'audio/webm' with the Opus codec is efficient and widely supported.
            mimeType: 'audio/webm;codecs=opus',

            // Adjust the audio bitrate (in bits per second) to control quality and file size.
            // For voice recording in a quiet environment, 48000 bps (48 kbps) is a good balance.
            // Higher values (e.g., 128000 for 128 kbps) are better for music.
            audioBitsPerSecond: 48000
        };

        // Determine the file extension from the MIME type
        const fileExtension = options.mimeType.split('/')[1].split(';')[0];

        try {
            mediaRecorder = new MediaRecorder(stream, options);
        } catch (error) {
            console.error("Failed to create MediaRecorder:", error);
            alert("Could not create MediaRecorder. Your browser may not support the selected format.");
            return;
        }
        audioChunks = [];

        mediaRecorder.ondataavailable = event => {
            audioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            audioBlob = new Blob(audioChunks, { type: options.mimeType });
            audioFile = new File([audioBlob], `physician_audio.${fileExtension}`, { type: options.mimeType });
            const audioUrl = URL.createObjectURL(audioBlob);
            audioPlayback.src = audioUrl;
            audioPlayback.style.display = 'block';

            startRecordingButton.disabled = false;
            stopRecordingButton.disabled = true;
            uploadButton.disabled = false;
            audioFileInput.removeAttribute('required');
            audioFileInput.disabled = true;
        };

        mediaRecorder.start();
        startRecordingButton.disabled = true;
        stopRecordingButton.disabled = false;
        uploadButton.disabled = true;
        audioFileInput.disabled = true;
    }

    // Add an event listener to the file input. If the user chooses a file,
    // it should re-enable the 'required' attribute and clear any existing recording.
    if (audioFileInput) {
        audioFileInput.addEventListener('change', () => {
            if (audioFileInput.files.length > 0) {
                console.log("User selected a file. Clearing recording and enabling upload.");
                // Clear the recording
                audioFile = null;
                audioPlayback.src = '';
                audioPlayback.style.display = 'none';
                // Ensure the file input is considered the source of truth
                audioFileInput.setAttribute('required', 'required');
                uploadButton.disabled = false;
            }
        });
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
    }

    function handleFormSubmission(event) {
        // Only intercept if we have a recorded audio file.
        if (audioFile) {
            event.preventDefault();
            const formData = new FormData(audioForm);
            formData.append('audio_file', audioFile, audioFile.name);

            const request = new XMLHttpRequest();
            request.open(audioForm.method, audioForm.action, true);
            // Set a header to identify this as an AJAX request
            request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            request.onload = () => {
                if (request.status >= 200 && request.status < 300) {
                    try {
                        const response = JSON.parse(request.responseText);
                        if (response.success && response.form_id) {
                            // Construct the correct redirect URL relative to the form's action path
                            const actionUrl = new URL(audioForm.action);
                            const redirectUrl = `${actionUrl.pathname.substring(0, actionUrl.pathname.lastIndexOf('/'))}/view.php?id=${response.form_id}&encounter=${response.encounter_id}`;
                            window.location.href = redirectUrl;
                        } else {
                            console.error('Submission failed:', response.error);
                            alert('Submission failed: ' + (response.error || 'Unknown error'));
                        }
                    } catch (e) {
                        console.error('Could not parse JSON response:', e);
                        console.error('Response text:', request.responseText);
                        alert('An unexpected error occurred while processing the server response.');
                    }
                } else {
                    console.error('Upload failed with status:', request.status, request.statusText);
                    alert('Upload failed. Please try again.');
                }
            };
            request.onerror = () => {
                console.error('Network error during upload');
                alert('A network error occurred. Please check your connection and try again.');
            };

            request.send(formData);
        }
        // If no audioFile, let the form submit normally for manual uploads.
    }

    if (startRecordingButton) {
        console.log("Attaching click listener to start button.");
        startRecordingButton.addEventListener('click', startRecording);
    } else {
        console.error("Start recording button not found, cannot attach listener.");
    }

    if (stopRecordingButton) {
        stopRecordingButton.addEventListener('click', stopRecording);
    }

    if (audioForm) {
        audioForm.addEventListener('submit', handleFormSubmission);
    }
});