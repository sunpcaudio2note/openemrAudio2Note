# Security and HIPAA Compliance

This document outlines the policies and procedures that Sun PC Solutions LLC has implemented to ensure the confidentiality, integrity, and availability of Protected Health Information (PHI) in compliance with the Health Insurance Portability and Accountability Act (HIPAA) of 1996.

## Privacy and Data Handling

The `Audio2Note` module is designed to adhere to the principles of the HIPAA Privacy Rule. 

*   **Data Flow:** When a user uploads an audio file, it is transmitted securely over HTTPS to a designated third-party transcription and note generation service. The module's polling service then retrieves the text-based data and integrates it into the patient's clinical record within OpenEMR. The original audio file is not permanently stored within OpenEMR's file system; it is handled as a transient data element for the purpose of processing.
*   **Minimum Necessary:** The module collects only the PHI required to fulfill its specific purpose. The only PHI disclosed to the external transcription service is the audio recording itself or, in case of note summarization, three previous encounter notes, along with a unique, non-identifying instance ID for licensing purposes. No patient demographic information (name, DOB, etc.) is transmitted.

## Security Measures

The module's security is built upon a combination of administrative, physical, and technical safeguards.

*   **Transmission Security:** All communication between the OpenEMR module and the external transcription service is conducted over HTTPS, ensuring end-to-end encryption of data in transit.
*   **Data-at-Rest Encryption:** Sensitive configuration data, specifically the license and API keys, are encrypted at rest in the `audio2note_config` database table using strong, industry-standard authenticated encryption (ChaCha20-Poly1305). The master encryption key is securely generated and stored in the database, ensuring it is unique to your OpenEMR instance.
*   **Access Control:** The module inherits and is governed entirely by OpenEMR's built-in Role-Based Access Control (RBAC) system. A user's ability to access the module's features is determined by their existing permissions in OpenEMR.

## Audit Controls

The system provides a clear and auditable trail for every transaction. The `form_audio_to_note` table within the OpenEMR database serves as the primary internal audit log, linking every transaction to a specific patient, encounter, and user.

## Business Associate Agreement (BAA)

A formal Business Associate Agreement (BAA) is required with the external transcription service, contractually obligating them to protect PHI in accordance with HIPAA. It is the responsibility of the Covered Entity deploying the module to ensure such an agreement is in place.

For more detailed information on our HIPAA compliance policies, please refer to the `Hipaa documentation.md` file.