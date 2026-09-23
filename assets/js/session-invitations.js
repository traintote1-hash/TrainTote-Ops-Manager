const qr = document.getElementById('inviteQr');
if (qr) new QRCode(qr, {text: qr.dataset.url, width: 256, height: 256, correctLevel: QRCode.CorrectLevel.M});
const inviteType = document.getElementById('inviteType');
const inviteEmail = document.getElementById('inviteEmail');
const inviteAssignment = document.getElementById('inviteAssignment');
if (inviteType) {
    const updateInviteFields = () => {
        const personal = inviteType.value === 'email';
        inviteEmail.disabled = !personal;
        inviteEmail.required = personal;
        inviteAssignment.disabled = !personal;
    };
    inviteType.addEventListener('change', updateInviteFields);
    updateInviteFields();
}
const copy = document.getElementById('copyInvite');
if (copy) copy.addEventListener('click', async () => {
    const link = document.getElementById('inviteLink');
    try { await navigator.clipboard.writeText(link.value); copy.textContent = 'Copied'; }
    catch { link.select(); copy.textContent = 'Select and copy the link above'; }
});
