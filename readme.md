# Audio2Note for OpenEMR

[![Build Status](https://img.shields.io/badge/build-passing-brightgreen)](https://github.com/sunpcaudio2note/openemrAudio2Note)
[![Compatibility](https://img.shields.io/badge/OpenEMR-v7.0+-blue)](https://www.open-emr.org)
[![Version](https://img.shields.io/badge/version-1.0.0-blue)](https://github.com/sunpcaudio2note/openemrAudio2Note)
[![License](https://img.shields.io/badge/license-MIT-blue)](https://github.com/sunpcaudio2note/openemrAudio2Note/blob/main/LICENSE)

## Overview

This repository contains the official OpenEMR module for the **[Audio2Note Service](https://github.com/sunpcaudio2note/audio2note)**. It is a powerful tool for healthcare professionals using OpenEMR, designed for Internal Medicine and related subspecialties, but with a modular design for future expansion.

This module streamlines the clinical documentation process by allowing you to convert audio recordings from patient encounters into structured clinical notes directly within the patient's chart.

## What It Does

*   **Audio to Note:** Upload an audio file of your patient encounter, and the module will automatically generate a custom **Audio2Note SOAP Note** or a **History and Physical Note**, complete with suggested ICD-10, CPT, and E/M codes.

    ![Example of a generated SOAP Note](docs/openemr/images/SOAP.png "Generated SOAP Note")
    *Example of a generated SOAP Note.*

    ![Example of a generated History and Physical Note](docs/openemr/images/historyphysical.png "Generated History and Physical Note")
    *Example of a generated History and Physical Note.*

*   **Chart Summarization:** Quickly get up to speed on a patient by generating a summary of their three most recent clinical notes.

    ![Chart Summarization Feature](docs/openemr/images/summary.png "Chart Summarization")
    *Generate a summary of recent clinical notes.*

*   **Seamless Integration:** The generated notes are automatically filed into the correct patient's chart, with real-time status updates on the progress of your note.

    ![Real-time update showing note processing](docs/openemr/images/realTimeUpdates.png "Real-time update notification")
    *Real-time update showing the note is being processed.*

## How It Works

1.  **Navigate to Audio2Note:** From within a patient encounter, navigate to `Clinical` --> `Audio2Note`.

    ![Audio2Note Menu Location](docs/openemr/images/1menu.png "Audio2Note Menu Location")

2.  **Provide Audio & Choose Note Type:** You can either upload a pre-recorded audio file or record one in real-time. Then, select the type of note you want to generate.

    ![File Upload and Note Type Selection](docs/openemr/images/2uploadfile.png "File Upload and Note Type Selection")

3.  **Generate Note:** Click "Upload and Transcribe." The note will be automatically populated in the appropriate chart when processing is complete.

## Why Use Audio2Note?

*   **Physician-Led Development:** The module is actively used and improved by a practicing physician, ensuring it meets the real-world needs of clinicians.
*   **Reliable Data:** The AI models use custom datasets produced from peer-reviewed sources.
*   **Privacy-Focused:** We use self-hosted software and small-scale AI models, meaning your information does not leave our servers.
*   **Secure by Design:** Sensitive data is protected with strong encryption (ChaCha20-Poly1305) and PHI is securely erased from our servers after processing.
*   **Open and Extensible:** The module is built on open-source software and is designed to be modular.

## Installation

### Step 1: Obtain a License

First, obtain a license from our website: **[https://www.audio2note.org](https://www.audio2note.org)**

A 10-day free trial period is included with every license. You will receive a **License Key**, **API Consumer Key**, and **API Consumer Secret** required to activate the module.

### Step 2: Install the Module

1.  **Decompress Files:** Place the `openemrAudio2Note_installer.tar.gz` package into your OpenEMR webroot directory (e.g., `/var/www/html/openemr/`). Then, from within that directory, run the following command:

    ```bash
    tar -xzvf openemrAudio2Note_installer.tar.gz
    ```

2.  **Finalize Installation:** Open your web browser and navigate to the installer script at `https://<your_openemr_url>/install.php`. Follow the on-screen instructions.

3.  **Activate and Configure:**
    *   In OpenEMR, navigate to `Modules -> Manage Modules` and activate the "Audio2Note" module.
    *   Go to `Modules -> Audio2Note -> Settings` to enter your license and API keys.

## Legal Disclaimer

This module is a clinical documentation aid. By using this module, you agree to the terms and conditions outlined in our [Legal Disclaimer](docs/Legal%20Disclaimer%20for%20Audio2Note%20Module.md).

## Contributing

This module is designed to be a robust and seamless bridge between OpenEMR and the Audio2Note service. If you are interested in contributing, please reach out via our main project's [Contact Form](https://www.audio2note.org/?page_id=136).
