const fs = require('node:fs');
const path = require('node:path');
const {setTimeout: delay} = require('node:timers/promises');
const {Client, LocalAuth, MessageMedia} = require('whatsapp-web.js');
const QRCode = require('qrcode');
const {envelope} = require('./relay');
const dir = path.resolve(__dirname,'../storage/app/private/report-bot');
fs.mkdirSync(dir,{mode:0o700,recursive:true});
const token=fs.readFileSync(path.join(dir,'token'),'utf8').trim();
const now=()=>Math.floor(Date.now()/1000);
let state={status:'Memulai WhatsApp Web'};
function publish(changes={}) {
    state={...state,...changes,updated:now()};
    const file=path.join(dir,'state.json');
    fs.writeFileSync(file+'.new',JSON.stringify(state),{mode:0o600});
    fs.renameSync(file+'.new',file);
}
const client=new Client({authStrategy:new LocalAuth({dataPath:path.join(dir,'session')}),
    puppeteer:{headless:true,args:['--disable-dev-shm-usage',...(process.env.WA_NO_SANDBOX==='1'?['--no-sandbox']:[])]}});
publish();
setInterval(()=>publish(),20000);
client.on('qr',async value=>{
    const qr=await QRCode.toDataURL(value,{width:360,margin:4});
    publish({status:'Menunggu pemindaian QR',qr:qr.split(',')[1],qrAt:now(),groups:[]});
});
client.on('authenticated',()=>publish({status:'Menyiapkan sesi',qr:null}));
async function groups() {
    if(state.status!=='Terhubung') return;
    const chats=await client.getChats();
    publish({groups:chats.filter(chat=>chat.isGroup).map(chat=>({id:chat.id._serialized,name:chat.name}))});
}
client.on('ready',()=>{publish({status:'Terhubung',qr:null}); groups().catch(()=>console.error('Daftar grup akan dicoba lagi.'));});
setInterval(()=>groups().catch(()=>console.error('Gagal memperbarui daftar grup.')),60000);
client.on('disconnected',()=>{publish({status:'Terputus',qr:null});process.exit(1);});
client.on('auth_failure',()=>{publish({status:'Penautan gagal',qr:null});process.exit(1);});
async function forward(payload) {
    for(let attempt=0;attempt<4;attempt++) {
        try {
            const res=await fetch(process.env.REPORT_ENDPOINT || 'http://127.0.0.1:8094/api/laporan/masuk',{method:'POST',
                headers:{Authorization:'Bearer '+token,'Content-Type':'application/json',Accept:'application/json'},
                body:JSON.stringify(payload),signal:AbortSignal.timeout(45000)});
            if(res.status>=500) throw new Error('Laravel tidak tersedia');
            return res;
        } catch(error) {
            if(attempt===3) throw error;
            await delay(1000*2**attempt);
        }
    }
}
client.on('message',async message=>{
    try {
        const payload=await envelope(client,message);
        if(!payload) return;
        const response=await forward(payload);
        if(response.status===204) return;
        if(!response.ok) {console.error('Laravel menolak pesan: HTTP',response.status);return;}
        if(response.headers.get('content-type')?.startsWith('application/pdf')) {
            if(payload.group) return;
            const filename=decodeURIComponent(response.headers.get('x-report-filename') || 'Laporan.pdf');
            const file=new MessageMedia('application/pdf',Buffer.from(await response.arrayBuffer()).toString('base64'),filename);
            await client.sendMessage(message.from,file,{sendMediaAsDocument:true});
        } else {
            const {reply,media}=await response.json();
            if(media) {
                const file=new MessageMedia(media.mime,media.data,media.name);
                const image=['image/png','image/jpeg','image/webp'].includes(media.mime);
                await client.sendMessage(message.from,file,{caption:reply,sendMediaAsDocument:!image});
            } else if(reply) await message.reply(reply);
        }
    } catch {console.error('Pemrosesan pesan gagal. Periksa koneksi layanan.');}
});
client.initialize().catch(error=>{console.error('Gagal memulai browser:',error.name,String(error.message).slice(0,650));process.exit(1);});
async function deliverZoomNotifications() {
    if (!client.info) return;
    const endpoint=(process.env.REPORT_ENDPOINT || 'http://127.0.0.1:8094/api/laporan/masuk').replace('/laporan/masuk','/zoom/outbox');
    const response=await fetch(endpoint,{headers:{Authorization:'Bearer '+token},signal:AbortSignal.timeout(10000)});
    if(!response.ok)return;
    for(const item of await response.json()) {
        try {
            await client.sendMessage(item.phone+'@c.us',item.body);
            await fetch(endpoint+'/'+item.id+'/sent',{method:'POST',headers:{Authorization:'Bearer '+token},signal:AbortSignal.timeout(10000)});
        }catch(error){console.error('Pengiriman notifikasi Zoom gagal:',error.message);break;}
    }
}
setInterval(()=>deliverZoomNotifications().catch(e=>console.error('Outbox Zoom:',e.message)),10000);
