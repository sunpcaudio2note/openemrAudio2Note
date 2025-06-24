# OpenEMR Audio2Note Integration

## What It Is

The Audio2Note module is a powerful tool for healthcare professionals using OpenEMR. It is designed for Internal Medicine and related subspecialties, but its modular design allows for easy expansion to other specialties in the future.

This module streamlines the clinical documentation process by allowing you to convert audio recordings from patient encounters into structured clinical notes directly within the patient's chart.

## What It Does

*   **Audio to Note:** Upload an audio file of your patient encounter, and the module will automatically generate a custom **Audio2Note SOAP Note** or a **History and Physical Note**, complete with suggested ICD-10, CPT, and E/M codes.
*   **Chart Summarization:** Quickly get up to speed on a patient by generating a summary of their three most recent clinical notes.
*   **Seamless Integration:** The generated notes are automatically filed into the correct patient's chart, even if you have moved on to other tasks.

## Why Use Audio2Note?

*   **Physician-Led Development:** The module is actively used and improved by a practicing physician, ensuring it meets the real-world needs of clinicians.
*   **Reliable Data:** The Artificial Intelligence models use custom datasets that are produced only from peer-reviewed sources.
*   **Privacy-Focused:** We prioritize the security of your patients' data.
    *   We use only self-hosted software, including small-scale Artificial Intelligence models (unlike large-scale enterprise models such as Gemini, ChatGPT, and so on). This means that your information does not leave our servers and is not shared with anyone.
    *   For detailed information on our security practices and HIPAA compliance, please see our [SECURITY.md](docs/SECURITY.md) file.
*   **Secure by Design:** All sensitive configuration data, such as API and license keys, are protected with strong, industry-standard encryption (ChaCha20-Poly1305) and stored securely in your OpenEMR database.
    *   Not only do we use encryption and other methods to protect your information, your Protected Health Information stays on our servers only for the duration it takes to produce the note. It is then securely erased from our servers.
*   **Open and Extensible:** The module is built on open-source software and is designed to be modular, allowing for the easy addition of new features.
*   **Simple and Powerful:** Record the encounter, then upload the file, and you're done. No need to wait for the process of producing a note to finish — just move on to your next patient. The note will be automatically populated in the appropriate chart.

## Legal Disclaimer

This module is a clinical documentation aid and does not replace the professional judgment of a licensed caregiver. By using this module, you agree to the terms and conditions outlined in our [Legal Disclaimer](docs/Legal%20Disclaimer%20for%20Audio2Note%20Module.md).

## Installation

First, obtain a license from our website:

`https://www.audio2note.org/?page_id=93`

1.  **Obtain License and API Keys:** A 10-day free trial period is included with every license. Add the subscription to the cart (no billing will occur until the trial period is over). You can cancel your subscription anytime.

2.  The license key, API consumer Key, and API consumer secret will be required to activate the module in OpenEMR. Note that every license can be activated only once per OpenEMR instance.

The module uses a simple, two-step installation process:

1.  **Decompress Files:** Place the `openemrAudio2Note_installer.tar.gz` package into your OpenEMR webroot directory (e.g., `/var/www/html/openemr/`). Then, from within that directory, run the following command:

    ```bash
    tar -xzvf openemrAudio2Note_installer.tar.gz
    ```

2.  **Finalize Installation:** Open your web browser and navigate to the installer script at `https://<your_openemr_url>/install.php`. Follow the instructions provided by this script.
    After a successful installation, you will be prompted to securely delete the `install.php` script.

## Planned Development

We are continuously working to expand the module's capabilities. Future features include:
*   A transcript-only function for dictation.

For more detailed information on how to use the module, please refer to the [How to Use](docs/How%20to%20Use.md) file.
For more detailed information on our HIPAA compliance policies, please refer to the [HIPAA Documentation](docs/Hipaa%20documentation.md) file.
