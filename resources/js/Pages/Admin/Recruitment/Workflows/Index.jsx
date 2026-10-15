import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { Background, Controls, Handle, MarkerType, MiniMap, Position, ReactFlow, addEdge, useEdgesState, useNodesState } from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { FiActivity, FiCheckCircle, FiGitBranch, FiLayers, FiUsers } from 'react-icons/fi';
import Layout from '../Layout';

const tones = {
    purple: { border: '#713080', background: '#24132d', text: '#edb4f8' },
    blue: { border: '#3f5e9a', background: '#17243d', text: '#b9cbff' },
    green: { border: '#2d8669', background: '#12372e', text: '#9af2cb' },
    amber: { border: '#82662e', background: '#332918', text: '#f1d596' },
    red: { border: '#7d3144', background: '#321622', text: '#ffb1bd' },
};

function StageNode({ data }) {
    const color = tones[data.tone] || tones.purple;
    return <div className="min-w-[190px] rounded-xl border p-4 shadow-[0_12px_28px_rgba(0,0,0,.22)]" style={{ borderColor: color.border, background: color.background, color: color.text }}><Handle type="target" position={Position.Left} className="!h-2.5 !w-2.5 !border-2 !border-[#090a10] !bg-[#d56bea]" /><div className="flex items-start justify-between gap-3"><span className="text-[11px] font-semibold uppercase tracking-[.15em]">{data.label}</span>{typeof data.count === 'number' && <span className="rounded-full bg-black/20 px-2 py-1 text-xs font-semibold">{data.count}</span>}</div><p className="mt-3 text-xs opacity-75">{data.description}</p><span className="mt-4 flex items-center gap-1 text-[10px] uppercase tracking-[.14em] opacity-60"><FiActivity size={12} /> {data.kind === 'status' ? 'Estado real' : 'Paso operativo'}</span><Handle type="source" position={Position.Right} className="!h-2.5 !w-2.5 !border-2 !border-[#090a10] !bg-[#d56bea]" /></div>;
}

const nodeTypes = { stage: StageNode };

function createGraph(flow) {
    const nodes = flow.nodes.map((node, index) => ({ id: node.key, type: 'stage', position: { x: index * 245, y: 120 }, data: node }));
    flow.branches.forEach((node, index) => {
        const sourceIndex = flow.nodes.findIndex((stage) => stage.key === node.from);
        nodes.push({ id: node.key, type: 'stage', position: { x: Math.max(0, sourceIndex) * 245 + index * 245, y: 360 }, data: node });
    });
    const edges = flow.nodes.slice(0, -1).map((node, index) => ({ id: `${node.key}-${flow.nodes[index + 1].key}`, source: node.key, target: flow.nodes[index + 1].key, type: 'smoothstep', animated: index >= 4, markerEnd: { type: MarkerType.ArrowClosed, color: '#b83bd4' }, style: { stroke: '#9d36b6', strokeWidth: 2 } }));
    flow.branches.forEach((node) => {
        const source = flow.nodes.find((stage) => stage.key === node.from)?.key || flow.nodes[flow.nodes.length - 2]?.key;
        edges.push({ id: `${source}-${node.key}`, source, target: node.key, type: 'smoothstep', label: 'bifurca', labelStyle: { fill: '#aaa5b5', fontSize: 10 }, markerEnd: { type: MarkerType.ArrowClosed, color: '#745080' }, style: { stroke: '#745080', strokeWidth: 1.5, strokeDasharray: '5 4' } });
    });
    return { nodes, edges };
}

function WorkflowBoard({ flow }) {
    const graph = useMemo(() => createGraph(flow), [flow]);
    const [nodes, setNodes, onNodesChange] = useNodesState(graph.nodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(graph.edges);
    const onConnect = useCallback((connection) => setEdges((current) => addEdge({ ...connection, type: 'smoothstep', markerEnd: { type: MarkerType.ArrowClosed, color: '#b83bd4' }, style: { stroke: '#b83bd4', strokeWidth: 2 } }, current)), [setEdges]);
    return <div className="mt-6 overflow-hidden rounded-2xl border border-[#292d39] bg-[#0d0f16] shadow-[0_18px_55px_rgba(0,0,0,.18)]"><div className="flex flex-col justify-between gap-3 border-b border-[#292d39] bg-[#11131c] px-5 py-5 sm:flex-row sm:items-start sm:px-7"><div><div className="flex items-center gap-2"><FiGitBranch className="text-[#d56bea]" size={18} /><h2 className="font-editorial text-2xl text-white">{flow.name}</h2></div><p className="mt-2 max-w-2xl text-sm text-[#858a99]">{flow.description}</p></div><span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#4d2a58] bg-[#25132e] px-3 py-1.5 text-[10px] uppercase tracking-[.16em] text-[#e8a4f4]"><FiLayers size={13} /> Mapa operativo</span></div><div className="h-[560px]" style={{ background: '#0d0f16' }}><ReactFlow nodes={nodes} edges={edges} nodeTypes={nodeTypes} onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} onConnect={onConnect} fitView fitViewOptions={{ padding: .2 }} proOptions={{ hideAttribution: true }} defaultEdgeOptions={{ type: 'smoothstep' }}><Background color="#252936" gap={24} size={1} /><Controls className="workflow-controls" /><MiniMap nodeColor={(node) => tones[node.data?.tone]?.border || tones.purple.border} maskColor="rgba(9,10,16,.75)" className="workflow-minimap" /></ReactFlow></div><div className="flex flex-wrap items-center gap-4 border-t border-[#292d39] bg-[#11131c] px-5 py-4 text-[10px] uppercase tracking-[.14em] text-[#777d8f] sm:px-7"><span>Arrastra los nodos</span><span>Conecta desde los puntos laterales</span><span>Los círculos indican estados reales</span><span>Usa la rueda para zoom</span></div></div>;
}

export default function Index({ flows, summary }) {
    const [selected, setSelected] = useState(flows[0]?.id);
    const flow = flows.find((item) => item.id === selected) || flows[0];
    return <><Head title="Workflows" /><Layout><div className="mx-auto max-w-[1440px]"><div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Configuración</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Workflows</h1><p className="mt-2 max-w-2xl text-sm text-[#969baa]">Consulta los flujos reales y los pasos operativos de reclutamiento, horarios, rooms, documentos, calendario y accesos.</p></div><div className="flex items-center gap-2 rounded-lg border border-[#292d39] bg-[#11131c] px-3 py-2 text-xs text-[#9a9eac]"><FiCheckCircle className="text-[#8ff0bd]" size={15} /> Estados sincronizados con candidatos</div></div><div className="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><FiUsers className="text-[#d56bea]" /><p className="mt-4 text-2xl text-white">{summary.total}</p><p className="mt-1 text-xs text-[#858a99]">Candidatos en los flujos</p></div><div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><FiActivity className="text-[#b9cbff]" /><p className="mt-4 text-2xl text-white">{summary.inProgress}</p><p className="mt-1 text-xs text-[#858a99]">En proceso</p></div><div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><FiCheckCircle className="text-[#8ff0bd]" /><p className="mt-4 text-2xl text-white">{summary.admitted}</p><p className="mt-1 text-xs text-[#858a99]">Admitidos</p></div><div className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-5"><FiLayers className="text-[#8ff0bd]" /><p className="mt-4 text-2xl text-white">{summary.active}</p><p className="mt-1 text-xs text-[#858a99]">Activos</p></div></div><div className="mt-8 flex flex-wrap gap-2 border-b border-[#292d39] pb-4">{flows.map((item) => <button type="button" key={item.id} onClick={() => setSelected(item.id)} className={`rounded-lg border px-4 py-2.5 text-xs transition ${selected === item.id ? 'border-[#a92ad8] bg-[#55166e] text-white' : 'border-[#343044] text-[#aaa5b5] hover:border-[#a92ad8] hover:text-white'}`}>{item.name}</button>)}</div>{flow && <WorkflowBoard key={flow.id} flow={flow} />}</div></Layout></>;
}

