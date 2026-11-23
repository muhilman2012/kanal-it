const test=require('node:test');
const assert=require('node:assert/strict');
const {envelope}=require('./relay');
const client={info:{wid:{_serialized:'628110000000@c.us'}}, getContactLidAndPhone:async ([id])=>
    [{pn:id==='900@lid'?'628110000000@c.us':'628220000000@c.us'}]};
const message={type:'chat',from:'123@g.us',author:'901@lid',id:{_serialized:'a'},timestamp:1000,
    mentionedIds:['900@lid'],body:'@900 Cek jaringan'};
test('group mention and sender resolve from LID',async ()=>{
    const data=await envelope(client,message);
    assert.equal(data.phone,'628220000000');
    assert.equal(data.text,'Cek jaringan');
    assert.equal(data.group,'123@g.us');
});
test('ignore unmentioned groups, status, outgoing messages and channels',async ()=>{
    for(const overrides of [{mentionedIds:[]},{isStatus:true},{fromMe:true},{from:'123@newsletter'}])
        assert.equal(await envelope(client,{...message,...overrides}),undefined);
});
test('private chat needs no mention',async ()=>{
    assert.equal((await envelope(client,{...message,from:'628220000000@c.us',mentionedIds:[]})).group,null);
});
test('allow explicit group commands without claiming a mention',async ()=>{
    for (const body of ['/penting','/penting 3','/cuti 25 nov 2026']) {
        const result=await envelope(client,{...message,body,mentionedIds:[]});
        assert.equal(result.mentioned,false);
        assert.equal(result.text,body);
    }
    assert.equal(await envelope(client,{...message,body:'/pentingnya',mentionedIds:[]}),undefined);
});
