const direct = /^\d+@(c\.us|lid)$/;
async function envelope(client, message) {
    if (message.fromMe || message.isStatus || message.type !== 'chat') return;
    const group = message.from.endsWith('@g.us');
    if (!group && !direct.test(message.from)) return;
    const sender = group ? message.author : message.from;
    if (!sender || !direct.test(sender)) return;
    const asPhone = async id => id.endsWith('@c.us') ? id : (await client.getContactLidAndPhone([id]))[0]?.pn;
    let body = message.body || '';
    let mentioned=false;
    if (group) {
        const ids = (message.mentionedIds || []).map(id => typeof id === 'string' ? id : id._serialized);
        const own = client.info.wid._serialized;
        let matched;
        for (const id of ids) {
            if (!id || !direct.test(id)) continue;
            const candidate = await asPhone(id);
            if (id === own || candidate && candidate === await asPhone(own)) {
                matched = id;
                break;
            }
        }
        mentioned=Boolean(matched);
        if (!mentioned && !/^\/(?:zoom|setuju|tolak|akun|tutorial|penting|cuti|izin|sakit|pdf)(?:\s|$)/i.test(body.trim())) return;
        // Only strip the bot's mention, retaining other people mentioned in the activity.
        for (const id of matched ? new Set([matched,own,await asPhone(own)]) : []) {
            if (id) body=body.replace(new RegExp('@'+id.split('@')[0]+'(?![0-9])','g'),'');
        }
    }
    const pn=await asPhone(sender);
    if (!pn || !/^\d{8,15}@c\.us$/.test(pn)) return;
    return {phone:pn.split('@')[0],group:group?message.from:null,mentioned,
        id:message.id._serialized,text:body.trim() || ' ',timestamp:message.timestamp};
}
module.exports={envelope};