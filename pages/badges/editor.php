<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole(['super_admin', 'admin', 'staff']);

$companies = [];
$cres = $conn->query("SELECT DISTINCT company_name FROM companies ORDER BY company_name");
if ($cres) while ($r = $cres->fetch_assoc()) $companies[] = $r['company_name'];
if (empty($companies)) {
    $ca = $conn->query("SELECT DISTINCT company FROM attendees WHERE company != '' ORDER BY company LIMIT 20");
    if ($ca) while ($r = $ca->fetch_assoc()) $companies[] = $r['company'];
}
if (empty($companies)) $companies = ['Amazon Web Services', 'SAP', 'Del Monte'];

$edit_id = (int)($_GET['id'] ?? 0);
$saved_layout = 'null';
$saved_name = 'My Badge Template';
if ($edit_id) {
    $stmt = $conn->prepare("SELECT * FROM badge_templates WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $tpl = $stmt->get_result()->fetch_assoc();
    if ($tpl) {
        $saved_layout = $tpl['layout_json'] ?: 'null';
        $saved_name = $tpl['template_name'];
    }
}
$page_title = 'Badge Designer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Badge Designer &mdash; <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSET_VER ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    .bd-wrap { display: flex; gap: 14px; height: calc(100vh - 150px); min-height: 560px; }
    .bd-panel { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow-y: auto; flex-shrink: 0; }
    .bd-left  { width: 240px; }
    .bd-right { width: 210px; }
    .bd-canvas { flex: 1; min-width: 0; background-color: var(--color-bg); background-image: radial-gradient(circle at 1px 1px, rgba(92, 34, 73, .16) 1px, transparent 0); background-size: 22px 22px; border: 1px solid var(--color-border); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; position: relative; overflow: auto; }
    .bd-sec { padding: 14px; border-bottom: 1px solid var(--color-border-faint); }
    .bd-sec-label { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 10px; }
    .el-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .el-btn { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 11px 8px; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-text-muted); font-size: 11px; font-weight: 600; cursor: pointer; transition: background var(--dur-fast), border-color var(--dur-fast), color var(--dur-fast); }
    .el-btn i { font-size: 16px; color: var(--wine-mid); }
    .el-btn:hover { background: var(--color-primary-soft); border-color: var(--magenta); color: var(--wine-mid); }
    .prop-row { margin-bottom: 10px; }
    .prop-label { font-size: 10px; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; margin-bottom: 4px; display: block; }
    .prop-input, .prop-select, .pv-input, .co-select { width: 100%; padding: 7px 10px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 12px; outline: none; transition: border-color var(--dur-fast), box-shadow var(--dur-fast); }
    .pv-input, .co-select { margin-bottom: 6px; }
    .prop-input:focus, .prop-select:focus, .pv-input:focus, .co-select:focus { border-color: var(--magenta); box-shadow: var(--focus-ring); }
    .prop-inline { display: flex; gap: 6px; }
    .color-row { display: flex; align-items: center; gap: 8px; }
    .color-input { width: 34px; height: 30px; padding: 2px; border: 1px solid var(--color-border); border-radius: 7px; cursor: pointer; background: var(--color-surface); }
    .prop-btn { width: 100%; padding: 7px; background: var(--color-primary-soft); border: 1px solid transparent; border-radius: var(--radius-sm); color: var(--wine-mid); font-size: 12px; font-weight: 600; cursor: pointer; transition: background var(--dur-fast); }
    .prop-btn:hover { background: var(--pink-bright); color: var(--white); }
    .prop-btn.danger { background: var(--danger-bg); color: var(--danger-text); }
    .prop-btn.danger:hover { background: var(--danger); color: var(--white); }
    .align-row { display: flex; gap: 4px; }
    .align-row .prop-btn { flex: 1; padding: 6px; }
    .layer-item { display: flex; align-items: center; gap: 7px; padding: 7px 8px; border-radius: var(--radius-sm); cursor: pointer; transition: background var(--dur-fast); margin-bottom: 2px; }
    .layer-item:hover { background: var(--color-surface-alt); }
    .layer-item.active { background: var(--color-primary-soft); }
    .layer-icon { width: 20px; height: 20px; border-radius: 5px; background: var(--color-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--wine-mid); font-size: 10px; flex-shrink: 0; }
    .layer-name { flex: 1; font-size: 11.5px; color: var(--color-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .layer-del { width: 20px; height: 20px; border: none; background: none; color: var(--color-text-faint); cursor: pointer; border-radius: 4px; font-size: 10px; }
    .layer-del:hover { background: var(--danger); color: var(--white); }
    #badge { width: 360px; height: 225px; background: #fff; border-radius: 14px; position: relative; overflow: hidden; box-shadow: var(--shadow-lg); flex-shrink: 0; }
    .badge-el { position: absolute; cursor: move; user-select: none; outline: none; }
    .badge-el.selected { outline: 2px solid var(--magenta); outline-offset: 2px; }
    .badge-el:hover:not(.selected) { outline: 1px dashed rgba(194, 59, 142, .5); outline-offset: 2px; }
    .tpl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .tpl-card { border-radius: var(--radius-sm); cursor: pointer; border: 1px solid var(--color-border); aspect-ratio: 1.6/1; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--color-text-muted); background: var(--color-surface-alt); transition: background var(--dur-fast), border-color var(--dur-fast), color var(--dur-fast); }
    .tpl-card:hover, .tpl-card.active { border-color: var(--magenta); background: var(--color-primary-soft); color: var(--wine-mid); }
    .bd-toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; flex-wrap: wrap; }
    .name-input { height: var(--control-h-sm); padding: 0 14px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; color: var(--color-text-strong); outline: none; min-width: 220px; background: var(--color-surface); }
    .name-input:focus { border-color: var(--magenta); box-shadow: var(--focus-ring); }
    .swatch-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-top: 6px; }
    .swatch { height: 22px; border-radius: 5px; cursor: pointer; border: 1px solid var(--color-border); }
    .swatch:hover { outline: 2px solid var(--magenta); outline-offset: 1px; }
    .bd-export-btn { width: 100%; margin-bottom: 6px; }
    input[type=file] { display: none; }
    @media (max-width: 900px) {
        .bd-wrap { flex-direction: column; height: auto; }
        .bd-left, .bd-right { width: 100%; }
        .bd-canvas { min-height: 300px; padding: 20px 0; }
    }
    @media print { body * { visibility: hidden; } #badge, #badge * { visibility: visible; } #badge { position: fixed; top: 20px; left: 20px; box-shadow: none; } }
</style>
</head>
<body class="dashboard-body">
<div class="dashboard-container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="bd-toolbar">
            <input type="text" class="name-input" id="tplName" value="<?= htmlspecialchars($saved_name) ?>" placeholder="Template name">
            <input type="hidden" id="tplId" value="<?= $edit_id ?>">
            <div style="flex:1;"></div>
            <button class="btn-sm light" onclick="clearCanvas()"><i class="fas fa-redo"></i> Reset</button>
            <button class="btn-sm light" onclick="printBadge()"><i class="fas fa-print"></i> Print</button>
            <button class="btn-sm" onclick="saveTemplate()"><i class="fas fa-save"></i> Save Template</button>
        </div>

        <div class="bd-wrap">
            <div class="bd-panel bd-left">
                <div class="bd-sec">
                    <div class="bd-sec-label">Add Elements</div>
                    <div class="el-grid">
                        <button class="el-btn" onclick="addText()"><i class="fas fa-font"></i>Text</button>
                        <button class="el-btn" onclick="triggerUpload('logo')"><i class="fas fa-image"></i>Logo</button>
                        <button class="el-btn" onclick="triggerUpload('bg')"><i class="fas fa-fill-drip"></i>Background</button>
                        <button class="el-btn" onclick="addQR()"><i class="fas fa-qrcode"></i>QR Code</button>
                    </div>
                </div>
                <div class="bd-sec" id="propPanel">
                    <div class="bd-sec-label">Properties</div>
                    <div id="noSelection" class="muted" style="font-size:12px;text-align:center;padding:10px 0;">Select an element</div>
                    <div id="textProps" style="display:none;">
                        <div class="prop-row"><span class="prop-label">Font</span>
                            <select class="prop-select" id="propFont" onchange="applyProps()">
                                <option value="Plus Jakarta Sans">Jakarta Sans</option>
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Montserrat">Montserrat</option>
                            </select>
                        </div>
                        <div class="prop-row"><span class="prop-label">Size &amp; Weight</span>
                            <div class="prop-inline">
                                <input type="number" class="prop-input" id="propSize" value="18" min="6" max="120" onchange="applyProps()">
                                <select class="prop-select" id="propWeight" onchange="applyProps()" style="width:90px;">
                                    <option value="400">Regular</option><option value="600">SemiBold</option>
                                    <option value="700">Bold</option><option value="800">ExtraBold</option>
                                </select>
                            </div>
                        </div>
                        <div class="prop-row"><span class="prop-label">Color</span>
                            <div class="color-row">
                                <input type="color" class="color-input" id="propColor" value="#111111" oninput="applyProps()">
                                <input type="text" class="prop-input" id="propColorHex" value="#111111" onchange="syncColor()" style="font-size:11px;">
                            </div>
                        </div>
                        <div class="prop-row"><span class="prop-label">Align</span>
                            <div class="align-row">
                                <button class="prop-btn" onclick="applyAlign('left')">L</button>
                                <button class="prop-btn" onclick="applyAlign('center')">C</button>
                                <button class="prop-btn" onclick="applyAlign('right')">R</button>
                            </div>
                        </div>
                        <div class="prop-row"><span class="prop-label">Field Binding</span>
                            <select class="prop-select" id="propField" onchange="bindField()">
                                <option value="">None (static)</option>
                                <option value="name">Full Name</option>
                                <option value="company">Company</option>
                                <option value="designation">Designation</option>
                                <option value="code">Attendee Code</option>
                            </select>
                        </div>
                    </div>
                    <div id="deleteRow" style="display:none;margin-top:8px;">
                        <button class="prop-btn danger" onclick="deleteSelected()"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
                <div class="bd-sec">
                    <div class="bd-sec-label">Preview Data</div>
                    <input type="text" class="pv-input" id="pvName" value="Juan Dela Cruz" placeholder="Full Name" oninput="updateDynamic()">
                    <input type="text" class="pv-input" id="pvDesignation" value="Seminar 1" placeholder="Designation" oninput="updateDynamic()">
                    <input type="text" class="pv-input" id="pvCode" value="EVT0007" placeholder="Code" oninput="updateDynamic()">
                    <select class="co-select" id="pvCompany" onchange="updateDynamic()">
                        <?php foreach ($companies as $co): ?><option value="<?= htmlspecialchars($co) ?>"><?= htmlspecialchars($co) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="bd-sec">
                    <div class="bd-sec-label">Layers</div>
                    <div id="layerList"></div>
                </div>
            </div>

            <div class="bd-canvas" onclick="deselectAll(event)">
                <div id="badge"></div>
            </div>

            <div class="bd-panel bd-right">
                <div class="bd-sec">
                    <div class="bd-sec-label">Templates</div>
                    <div class="tpl-grid">
                        <div class="tpl-card active" onclick="applyTemplate('bold')" id="tpl-bold">Bold</div>
                        <div class="tpl-card" onclick="applyTemplate('minimal')" id="tpl-minimal">Minimal</div>
                        <div class="tpl-card" onclick="applyTemplate('elegant')" id="tpl-elegant">Elegant</div>
                        <div class="tpl-card" onclick="applyTemplate('event')" id="tpl-event">Event</div>
                    </div>
                </div>
                <div class="bd-sec">
                    <div class="bd-sec-label">Background Color</div>
                    <div class="color-row" style="margin-bottom:8px;">
                        <input type="color" class="color-input" id="bgColor" value="#ffffff" oninput="changeBgColor(this.value)">
                        <span class="muted" style="font-size:11px;">Badge BG</span>
                    </div>
                    <div class="swatch-grid">
                        <?php foreach (['#ffffff','#fbf5ec','#2e0f26','#431a38','#5c2249','#7a2f5f','#c23b8e','#e8579f'] as $sw): ?>
                        <div class="swatch" onclick="changeBgColor('<?= $sw ?>')" style="background:<?= $sw ?>;"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bd-sec">
                    <div class="bd-sec-label">Export</div>
                    <button class="btn-sm bd-export-btn" onclick="saveTemplate()"><i class="fas fa-save"></i> Save Template</button>
                    <button class="btn-sm light bd-export-btn" onclick="printBadge()"><i class="fas fa-print"></i> Print Badge</button>
                    <a class="btn-sm ghost bd-export-btn" href="<?= BASE_URL ?>/pages/badges/index.php"><i class="fas fa-folder"></i> All Templates</a>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="toast" id="toast"><div class="toast-dot"></div><span id="toastMsg"></span></div>
<input type="file" id="logoInput" accept="image/*" onchange="handleImageUpload(this,'logo')">
<input type="file" id="bgInput" accept="image/*" onchange="handleImageUpload(this,'bg')">

<script>
const SAVE_URL = '<?= BASE_URL ?>/api/badges/save_template.php';
const SAVED = <?= $saved_layout ?>;
let elements=[], selectedId=null, nextId=1, badgeW=360, badgeH=225;
const badge=document.getElementById('badge');
let toastTimer;
function showToast(msg,type='g'){const t=document.getElementById('toast');t.className='toast toast-'+type+' show';document.getElementById('toastMsg').textContent=msg;clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.classList.remove('show'),2600);}
function qrUrl(code){return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='+encodeURIComponent(code)+'&ecc=H&margin=4';}
function makeEl(config){const el={id:nextId++,...config};elements.push(el);renderEl(el);selectEl(el.id);renderLayers();return el;}
function addText(){makeEl({type:'text',content:'New Text',x:40,y:80,fontSize:18,fontFamily:'Plus Jakarta Sans',fontWeight:'600',color:'#111111',align:'left',_field:''});}
function addQR(){const code=document.getElementById('pvCode').value||'EVT0007';makeEl({type:'qr',x:258,y:70,size:90,content:code});}
function renderAll(){badge.querySelectorAll('.badge-el').forEach(e=>e.remove());elements.forEach(el=>renderEl(el));}
function renderEl(el){
  let ex=document.getElementById('el-'+el.id); if(ex) ex.remove();
  let node;
  if(el.type==='text'){
    node=document.createElement('div');node.contentEditable=true;node.textContent=el.content;
    node.style.cssText=`position:absolute;left:${el.x}px;top:${el.y}px;font-family:'${el.fontFamily}',sans-serif;font-size:${el.fontSize}px;font-weight:${el.fontWeight||'400'};color:${el.color};text-align:${el.align||'left'};min-width:40px;white-space:nowrap;cursor:move;line-height:1.3;`;
    node.addEventListener('input',()=>{const f=elements.find(e=>e.id===el.id);if(f&&!f._field)f.content=node.textContent;});
  } else if(el.type==='qr'){
    node=document.createElement('div');node.style.cssText=`position:absolute;left:${el.x}px;top:${el.y}px;width:${el.size}px;height:${el.size}px;cursor:move;background:#fff;border-radius:6px;overflow:hidden;`;
    const img=document.createElement('img');img.src=qrUrl(el.content||'EVT0007');img.style.cssText='width:100%;height:100%;pointer-events:none;';node.appendChild(img);
  } else if(el.type==='img'){
    node=document.createElement('img');node.src=el.src;node.style.cssText=`position:absolute;left:${el.x}px;top:${el.y}px;width:${el.w}px;height:${el.h}px;object-fit:contain;cursor:move;`;
  } else if(el.type==='rect'||el.type==='line'){
    node=document.createElement('div');node.style.cssText=`position:absolute;left:${el.x}px;top:${el.y}px;width:${el.w}px;height:${el.h}px;background:${el.fill};opacity:${el.opacity};border-radius:${el.radius||0}px;cursor:move;`;
  }
  node.id='el-'+el.id;node.className='badge-el'+(selectedId===el.id?' selected':'');node.dataset.id=el.id;
  makeDraggable(node,el);makeResizable(node,el);
  node.addEventListener('mousedown',e=>{e.stopPropagation();selectEl(el.id);});
  badge.appendChild(node);
}
function makeDraggable(node,el){let sx,sy,sex,sey;node.addEventListener('mousedown',e=>{sx=e.clientX;sy=e.clientY;sex=el.x;sey=el.y;function mv(e){el.x=Math.round(Math.max(0,sex+(e.clientX-sx)));el.y=Math.round(Math.max(0,sey+(e.clientY-sy)));node.style.left=el.x+'px';node.style.top=el.y+'px';}function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);}document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);});}
function makeResizable(node,el){let sx,sy,sw,sh;node.addEventListener('mousedown',e=>{const r=node.getBoundingClientRect();if(!(e.clientX>r.right-12&&e.clientY>r.bottom-12))return;e.stopPropagation();e.preventDefault();sx=e.clientX;sy=e.clientY;sw=el.w||el.size||80;sh=el.h||el.size||80;function mv(e){const nw=Math.max(20,sw+(e.clientX-sx));const nh=Math.max(10,sh+(e.clientY-sy));if(el.type==='qr')el.size=Math.round(nw);else{el.w=Math.round(nw);el.h=Math.round(nh);}renderEl(el);}function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);}document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);});}
function selectEl(id){selectedId=id;badge.querySelectorAll('.badge-el').forEach(n=>n.classList.toggle('selected',parseInt(n.dataset.id)===id));showProps(elements.find(e=>e.id===id));renderLayers();}
function deselectAll(e){if(e.target===badge||e.target.classList.contains('bd-canvas')){selectedId=null;badge.querySelectorAll('.badge-el').forEach(n=>n.classList.remove('selected'));showProps(null);renderLayers();}}
function showProps(el){
  document.getElementById('noSelection').style.display='none';document.getElementById('textProps').style.display='none';document.getElementById('deleteRow').style.display='none';
  if(!el){document.getElementById('noSelection').style.display='block';return;}
  document.getElementById('deleteRow').style.display='block';
  if(el.type==='text'){document.getElementById('textProps').style.display='block';document.getElementById('propFont').value=el.fontFamily||'Plus Jakarta Sans';document.getElementById('propSize').value=el.fontSize||18;document.getElementById('propWeight').value=el.fontWeight||'400';document.getElementById('propColor').value=el.color||'#111111';document.getElementById('propColorHex').value=el.color||'#111111';document.getElementById('propField').value=el._field||'';}
}
function applyProps(){const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;el.fontFamily=document.getElementById('propFont').value;el.fontSize=parseInt(document.getElementById('propSize').value);el.fontWeight=document.getElementById('propWeight').value;el.color=document.getElementById('propColor').value;document.getElementById('propColorHex').value=el.color;renderEl(el);}
function syncColor(){const hex=document.getElementById('propColorHex').value;if(/^#[0-9A-Fa-f]{6}$/.test(hex)){document.getElementById('propColor').value=hex;applyProps();}}
function applyAlign(a){const el=elements.find(e=>e.id===selectedId);if(!el)return;el.align=a;renderEl(el);}
function bindField(){const el=elements.find(e=>e.id===selectedId);if(!el||el.type!=='text')return;el._field=document.getElementById('propField').value;updateDynamic();}
function deleteSelected(){if(!selectedId)return;elements=elements.filter(e=>e.id!==selectedId);document.getElementById('el-'+selectedId)?.remove();selectedId=null;showProps(null);renderLayers();}
function renderLayers(){const list=document.getElementById('layerList');list.innerHTML='';[...elements].reverse().forEach(el=>{const div=document.createElement('div');div.className='layer-item'+(el.id===selectedId?' active':'');const ic=document.createElement('div');ic.className='layer-icon';ic.innerHTML=el.type==='text'?'<i class="fas fa-font"></i>':el.type==='qr'?'<i class="fas fa-qrcode"></i>':el.type==='img'?'<i class="fas fa-image"></i>':'<i class="fas fa-square"></i>';const nm=document.createElement('div');nm.className='layer-name';nm.textContent=el.type==='text'?(el._field?'['+el._field+']':el.content.substring(0,14)):el.type.toUpperCase()+' '+el.id;const del=document.createElement('button');del.className='layer-del';del.innerHTML='<i class="fas fa-times"></i>';del.onclick=(e)=>{e.stopPropagation();selectedId=el.id;deleteSelected();};div.onclick=()=>selectEl(el.id);div.appendChild(ic);div.appendChild(nm);div.appendChild(del);list.appendChild(div);});}
function triggerUpload(type){document.getElementById(type==='logo'?'logoInput':'bgInput').click();}
function handleImageUpload(input,type){const file=input.files[0];if(!file)return;const rd=new FileReader();rd.onload=e=>{if(type==='bg'){badge.style.backgroundImage=`url(${e.target.result})`;badge.style.backgroundSize='cover';badge.style.backgroundPosition='center';}else makeEl({type:'img',src:e.target.result,x:20,y:20,w:80,h:80});};rd.readAsDataURL(file);}
function changeBgColor(hex){badge.style.backgroundColor=hex;badge.style.backgroundImage='';document.getElementById('bgColor').value=hex;}
function updateDynamic(){const name=document.getElementById('pvName').value;const code=document.getElementById('pvCode').value;const company=document.getElementById('pvCompany').value;const designation=document.getElementById('pvDesignation').value;elements.forEach(el=>{if(el.type==='text'&&el._field){const map={name,company,code,designation};el.content=map[el._field]||el.content;const n=document.getElementById('el-'+el.id);if(n)n.textContent=el.content;}if(el.type==='qr'){el.content=code;const n=document.getElementById('el-'+el.id);if(n){const img=n.querySelector('img');if(img)img.src=qrUrl(code);}}});}
function applyTemplate(name){
  document.querySelectorAll('.tpl-card').forEach(c=>c.classList.remove('active'));document.getElementById('tpl-'+name)?.classList.add('active');
  elements=[];badge.querySelectorAll('.badge-el').forEach(e=>e.remove());nextId=1;badge.style.backgroundImage='';
  const n=document.getElementById('pvName').value||'Juan Dela Cruz';const code=document.getElementById('pvCode').value||'EVT0007';const co=document.getElementById('pvCompany').value||'Amazon';
  if(name==='bold'){badge.style.backgroundColor='#0f172a';makeEl({type:'rect',x:0,y:0,w:360,h:50,fill:'#7a2f5f',opacity:1,radius:0});makeEl({type:'text',content:'FULL CIRCLE EVENTS ASIA',x:16,y:16,fontSize:11,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'#ffffff',align:'left',_field:''});makeEl({type:'text',content:n,x:20,y:70,fontSize:24,fontFamily:'Plus Jakarta Sans',fontWeight:'800',color:'#ffffff',align:'left',_field:'name'});makeEl({type:'text',content:co,x:20,y:105,fontSize:13,fontFamily:'Plus Jakarta Sans',fontWeight:'600',color:'#e8579f',align:'left',_field:'company'});makeEl({type:'text',content:code,x:20,y:185,fontSize:11,fontFamily:'Plus Jakarta Sans',fontWeight:'400',color:'rgba(232,87,159,0.6)',align:'left',_field:'code'});makeEl({type:'qr',x:258,y:62,size:92,content:code});}
  else if(name==='minimal'){badge.style.backgroundColor='#ffffff';makeEl({type:'line',x:0,y:55,w:360,h:2,fill:'#7a2f5f',opacity:1,radius:0});makeEl({type:'text',content:n,x:30,y:70,fontSize:22,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'#2e0f26',align:'left',_field:'name'});makeEl({type:'text',content:co,x:30,y:102,fontSize:13,fontFamily:'Plus Jakarta Sans',fontWeight:'400',color:'#4a3d52',align:'left',_field:'company'});makeEl({type:'text',content:code,x:30,y:170,fontSize:11,fontFamily:'Plus Jakarta Sans',fontWeight:'400',color:'#9b8fa0',align:'left',_field:'code'});makeEl({type:'qr',x:258,y:62,size:92,content:code});}
  else if(name==='elegant'){badge.style.backgroundColor='#faf9f7';makeEl({type:'rect',x:0,y:0,w:8,h:225,fill:'#7a2f5f',opacity:1,radius:0});makeEl({type:'text',content:n,x:28,y:75,fontSize:22,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'#2e0f26',align:'left',_field:'name'});makeEl({type:'text',content:co.toUpperCase(),x:28,y:108,fontSize:10,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'#7a2f5f',align:'left',_field:'company'});makeEl({type:'line',x:28,y:125,w:200,h:1,fill:'#e9e3ea',opacity:1,radius:0});makeEl({type:'text',content:code,x:28,y:170,fontSize:11,fontFamily:'Plus Jakarta Sans',fontWeight:'400',color:'#4a3d52',align:'left',_field:'code'});makeEl({type:'qr',x:255,y:68,size:88,content:code});}
  else if(name==='event'){badge.style.backgroundColor='#2e0f26';makeEl({type:'rect',x:0,y:0,w:360,h:58,fill:'#7a2f5f',opacity:1,radius:0});makeEl({type:'text',content:'FULL CIRCLE EVENTS ASIA',x:16,y:20,fontSize:9,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'rgba(255,255,255,0.7)',align:'left',_field:''});makeEl({type:'text',content:n,x:20,y:80,fontSize:22,fontFamily:'Plus Jakarta Sans',fontWeight:'700',color:'#ffffff',align:'left',_field:'name'});makeEl({type:'text',content:co,x:20,y:112,fontSize:12,fontFamily:'Plus Jakarta Sans',fontWeight:'600',color:'#e8579f',align:'left',_field:'company'});makeEl({type:'line',x:20,y:135,w:200,h:1,fill:'rgba(232,87,159,0.4)',opacity:1,radius:0});makeEl({type:'text',content:code,x:20,y:175,fontSize:10,fontFamily:'Plus Jakarta Sans',fontWeight:'400',color:'rgba(255,255,255,0.3)',align:'left',_field:'code'});makeEl({type:'qr',x:258,y:65,size:92,content:code});}
  renderLayers();showToast('Template applied!','g');
}
function loadSaved(){if(!SAVED||!SAVED.elements)return false;elements=[];badge.querySelectorAll('.badge-el').forEach(e=>e.remove());nextId=1;if(SAVED.bg?.color)badge.style.backgroundColor=SAVED.bg.color;if(SAVED.size?.w){badge.style.width=SAVED.size.w+'px';badgeW=SAVED.size.w;}if(SAVED.size?.h){badge.style.height=SAVED.size.h+'px';badgeH=SAVED.size.h;}SAVED.elements.forEach(el=>{el.id=nextId++;elements.push(el);renderEl(el);});renderLayers();updateDynamic();return true;}
function clearCanvas(){appConfirm('Every element on the badge is removed. Saved templates are not affected.',{title:'Reset the canvas?',ok:'Reset',danger:true}).then(function(ok){if(!ok)return;elements=[];badge.querySelectorAll('.badge-el').forEach(e=>e.remove());nextId=1;selectedId=null;badge.style.backgroundColor='#ffffff';badge.style.backgroundImage='';showProps(null);renderLayers();});}
function saveTemplate(){
  const payload={id:parseInt(document.getElementById('tplId').value)||0,template_name:document.getElementById('tplName').value||'My Badge Template',elements,bg:{color:badge.style.backgroundColor||'#ffffff'},size:{w:badgeW,h:badgeH}};
  fetch(SAVE_URL,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.json()).then(d=>{if(d.success){showToast(d.message,'g');if(d.id)document.getElementById('tplId').value=d.id;}else showToast(d.message||'Save failed','r');}).catch(()=>showToast('Save failed.','r'));
}
function printBadge(){const w=window.open('','_blank');w.document.write(`<!DOCTYPE html><html><head><style>@media print{body{margin:0;}}body{display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}#badge{border-radius:14px;overflow:hidden;position:relative;box-shadow:0 4px 24px rgba(0,0,0,.2);}.badge-el{position:absolute;}.badge-el.selected{outline:none!important;}</style></head><body>${badge.outerHTML}<scr`+`ipt>window.onload=()=>window.print();</scr`+`ipt></body></html>`);w.document.close();}
document.addEventListener('keydown',e=>{if(e.key==='Delete'&&selectedId&&document.activeElement.contentEditable!=='true'){e.preventDefault();deleteSelected();}if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();saveTemplate();}});
window.addEventListener('DOMContentLoaded',()=>{if(!loadSaved())applyTemplate('bold');});
</script>
</body>
</html>
