import * as Ably from 'ably';

let realtime;
let realtimeAvailable = null;
const availabilityListeners = new Set();

function reportAvailability(available) {
    realtimeAvailable = available;
    availabilityListeners.forEach((listener) => listener(available));
}

export function subscribeToRealtime(onUpdate, eventName = null, onAvailabilityChange = null) {
    if (onAvailabilityChange) availabilityListeners.add(onAvailabilityChange);
    if (!import.meta.env.VITE_ABLY_ENABLED) {
        onAvailabilityChange?.(false);
        return () => onAvailabilityChange && availabilityListeners.delete(onAvailabilityChange);
    }
    if (onAvailabilityChange && realtimeAvailable !== null) onAvailabilityChange(realtimeAvailable);
    if (!realtime) {
        realtime = new Ably.Realtime({ authUrl: '/realtime/token', authMethod: 'GET' });
        realtime.connection.on((stateChange) => {
            if (stateChange.current === 'connected') reportAvailability(true);
            if (['failed', 'suspended'].includes(stateChange.current)) {
                reportAvailability(false);
                console.error('[Ably] conexión realtime no disponible:', stateChange.reason || stateChange.current);
            }
        });
    }
    const channelNames = (import.meta.env.VITE_ABLY_CHANNELS || import.meta.env.VITE_ABLY_CHANNEL || 'admin-leads').split(',').map((name) => name.trim()).filter(Boolean);
    const roomsChannel = import.meta.env.VITE_ABLY_ROOMS_CHANNEL || 'admin-rooms';
    if (!channelNames.includes(roomsChannel)) channelNames.push(roomsChannel);
    const channels = channelNames.map((name) => realtime.channels.get(name));
    const handler = (message) => {
        let data = message.data;
        if (typeof data === 'string') { try { data = JSON.parse(data); } catch { return; } }
        onUpdate(data, message.name);
    };
    channels.forEach((channel) => eventName ? channel.subscribe(eventName, handler) : channel.subscribe(handler));
    return () => {
        channels.forEach((channel) => eventName ? channel.unsubscribe(eventName, handler) : channel.unsubscribe(handler));
        if (onAvailabilityChange) availabilityListeners.delete(onAvailabilityChange);
    };
}

export function subscribeToLeadUpdates(onUpdate) {
    return subscribeToRealtime(onUpdate, 'lead.status_changed');
}

export function subscribeToCandidateDocuments({ authUrl, channelName, onUpdate, onAvailabilityChange }) {
    if (!import.meta.env.VITE_ABLY_ENABLED || !authUrl || !channelName) {
        onAvailabilityChange?.(false);
        return () => {};
    }

    const client = new Ably.Realtime({ authUrl, authMethod: 'GET' });
    const channel = client.channels.get(channelName);
    const handler = (message) => {
        let data = message.data;
        if (typeof data === 'string') { try { data = JSON.parse(data); } catch { return; } }
        onUpdate?.(data, message.name);
    };

    client.connection.on((stateChange) => {
        if (stateChange.current === 'connected') onAvailabilityChange?.(true);
        if (['failed', 'suspended'].includes(stateChange.current)) onAvailabilityChange?.(false);
    });
    channel.subscribe(handler);

    return () => {
        channel.unsubscribe(handler);
        client.close();
    };
}

