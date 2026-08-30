import { ref } from 'vue';

export function usePosDevice() {
    const getDeviceId = (): string => {
        let deviceId = localStorage.getItem('pos_device_id');

        if (!deviceId) {
            deviceId = `POS-WEB-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
            localStorage.setItem('pos_device_id', deviceId);
        }

        return deviceId;
    };

    const deviceId = ref(getDeviceId());

    return {
        deviceId,
        getDeviceId,
    };
}
