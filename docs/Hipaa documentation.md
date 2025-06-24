# HIPAA Compliance Documentation for Sun PC Solutions LLC (Simplified)

## Policies and Procedures

This document explains how Sun PC Solutions LLC protects your health information, following rules like HIPAA. These rules help make sure that your private health information, especially when using our `Audio2Note` module, is kept safe and private.

### Privacy Policy

The `Audio2Note` module handles your audio recordings, which contain health information, in a safe way. When you upload an audio file using the "Audio to Note" form, it is sent securely to a special service that turns speech into text and helps create clinical notes. This is done to make the module work. The module then takes this text and puts it into your patient record in OpenEMR. The original audio file is not kept permanently; it's only used for processing.

### Minimum Necessary Documentation

The `Audio2Note` module only collects, uses, and shares the minimum amount of health information needed for its job.

*   **Information Collected:** For each audio recording, the system collects the audio file itself (which has health information), and numbers that link it to the right patient, visit, form, and user in OpenEMR. This is the least amount of information needed to connect the audio to your record.
*   **Information Used:** The audio file is used only by the external service to create a text transcript and notes. The linking numbers help put the finished note into the correct patient's chart.
*   **Information Shared:** The only health information shared with the external transcription service is the audio recording itself, or sometimes previous notes for summarization. No personal details like your name or birthdate are sent.

### Records of Disclosures

The module keeps track of when health information is shared.

*   **Internal Disclosure Log:** A form within the OpenEMR database keeps a record. For each audio file submitted, a record is created so that an auditable link is created between the initial audio submission and the resulting entry in the patient's chart.
*   **External Disclosure Log:** The external transcription service also keeps its own record, linking the job to the OpenEMR system it came from.

### Workforce Authorization Levels

The `Audio2Note` module uses OpenEMR's existing system for who can access what.

*   **Access Control:** If you can create or view a patient's clinical note in OpenEMR, you will have the same access to this module's features for that patient. The module works based on your existing permissions in OpenEMR.

### Risk Analysis and Risk Management

The `Audio2Note` module is designed to reduce risks by limiting how much health information is exposed. We have a special agreement, called a Business Associate Agreement (BAA), with the service that does the transcription.

*   **Risk Reduction:**
    *   **Less Data Shared:** Only the audio recording is shared with the external service, not your personal details, which reduces the risk if there's a problem.
    *   **Separate Systems:** The module uses OpenEMR's strong security, like its user logins and access rules. It doesn't create new ways to log in.
    *   **Service Security:** The transcription service is responsible for keeping the audio files secure, as agreed in our BAA.

### Security Policy

The module's security uses a mix of rules, physical protection, and technical safeguards, relying on OpenEMR's features and the module's design.

*   **Administrative Safeguards:** We have a formal agreement (BAA) with the external transcription service, making them legally responsible for protecting health information.
*   **Physical Safeguards:** The module is software, so its physical security depends on where OpenEMR and the transcription service are hosted.
*   **Technical Safeguards:**
    *   **Secure Sending:** All information sent between OpenEMR and the transcription service is encrypted, meaning it's scrambled so only the right people can read it.
    *   **Encrypted Data Storage:** Important settings, like the license and service keys, are encrypted when stored in the database. This encryption uses strong methods to keep them safe.

### Access Control Policy

The `Audio2Note` module does not have its own way of managing users or access. It uses OpenEMR's existing system.

*   **Permissions Inheritance:** Your ability to use the "Audio to Note" form, upload audio, and see the notes is controlled by your permissions in OpenEMR. If you can create or view a patient's notes, you can use this module for that patient.

### Audit Controls and Activity Reviews

The system keeps a clear record of every action, showing when health information is shared and how each transcription job progresses.

*   **Internal Audit Log:** The `form_audio_to_note` table in the OpenEMR database acts as the main internal record. Each time an audio file is sent, a new entry is made that links the transcription job to the patient, visit, and final note.
*   **External Audit Log:** The external service also keeps a matching record, linking their job to the OpenEMR system it came from.

### Contingency Plans

The plan for handling audio data if something goes wrong depends on the external service.

*   **Data Backup and Recovery:** Since audio files are not kept permanently in OpenEMR, the external transcription service is responsible for backing up and recovering the audio health information, as stated in the BAA. The final text note, once created, is stored in OpenEMR and is covered by the healthcare organization's own backup plans.

### HIPAA Security Official

The `Audio2Note` module doesn't have its own security official. The healthcare organization using OpenEMR is responsible for overall security, including how this module is used.

### Incident Response and Breach Notification

This section explains what to do if there's a security problem, like a breach of health information, following HIPAA rules. It separates responsibilities between the healthcare organization (who uses the module) and the external transcription service.

#### Division of Responsibilities

*   **External Service (Business Associate):** As per our agreement (BAA), the external service must keep all health information it handles secure. If there's a security problem or breach on their side, they must tell the healthcare organization quickly, as per the BAA and HIPAA rules.

*   **Healthcare Organization (Covered Entity):** The healthcare organization is responsible for its own response when they hear about a problem from the external service or find one themselves. They are ultimately responsible for deciding if a breach of unsecured health information happened, how big it is, and telling affected individuals, the government, and sometimes the media.

#### Breach Investigation and Notification Process

If the external service reports a security problem, the healthcare organization's HIPAA Security Official will start an investigation. The `Audio2Note` module helps by providing records to find all affected individuals.

The investigation would involve these steps:

1.  **Get Details from External Service:** The external service will provide information about the problem, including when it happened and any unique numbers for the affected data.

2.  **Check Internal Records:** The healthcare organization's technical staff will look at the `form_audio_to_note` table in the OpenEMR database. This table is the main record linking every transaction to a specific patient.

3.  **Find Affected Individuals:** Using the numbers from the external service, the healthcare organization can find out which patients' information was involved.

4.  **Decide What to Do and Notify:** With the list of affected patients, the healthcare organization can then follow its duties under the Breach Notification Rule.

#### Proactive Incident Detection

Besides reacting to problems reported by the external service, the healthcare organization can also look for potential issues themselves. The module regularly checks with the external service. Errors logged by this module—like not being able to connect, strange responses, or ongoing problems with many jobs—could mean a security event at the external service and should be looked into by the healthcare organization's HIPAA Security Official.

## Business Associate Agreements (BAAs)

Sun PC Solutions LLC may, in the future, require to expand for additional computational resources. In that case, a formal agreement (BAA) with any company that handles health information will be signed. This agreement makes them legally responsible for protecting the health information they process. It also explains their duties if there's a security problem or breach, making sure they follow HIPAA rules. The healthcare organization using the module is responsible for making sure this agreement is in place.

## Training Policy

Everyone at a healthcare organization using the `Audio2Note` module who has access to health information must get regular HIPAA training. This training covers the basic HIPAA rules, the organization's specific policies, and how to use the `Audio2Note` module correctly and securely. Training will be given when someone is hired and every year after that, or more often if rules or procedures change.

## Training Records

The healthcare organization must keep records of all HIPAA training completed by its staff. These records must show the training dates, what was covered, and who attended. These records must be kept for at least six years and shown to the Department of Health and Human Services (HHS) if asked.

## Sanctions Policy

The healthcare organization must have and use appropriate penalties for staff members who don't follow its HIPAA policies or this documentation. The penalty will depend on how serious the violation is and could range from warnings and more training to job termination and legal action, following the organization's rules.

## Record Retention

Sun PC Solutions LLC, and any healthcare organization using the `Audio2Note` module, must keep all HIPAA-related documents for at least six years from when they were created or last in effect. This includes policies, risk analyses, agreements, training records, and incident reports. All records will be kept in a safe and easy-to-access way.
