<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                My Dashboard
            </h2>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium
                {{ $user->device_limit > 0 && $user->vpnDevices->count() >= $user->device_limit ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-700' }}">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 7H7v6h6V7z"/><path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd"/>
                </svg>
                {{ $user->vpnDevices->count() }} of {{ $user->device_limit === 0 ? 'Unlimited' : $user->device_limit }} devices
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ── Flash Messages ─────────────────────────────────────────── --}}
            @if (session('success'))
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3">
                    <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-medium">{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <ul class="text-sm font-medium list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── Data Usage Card ─────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Data Usage This Month</h3>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-900">{{ $user->dataUsedGb() }} GB</span>
                            @if ($user->data_cap_mb > 0)
                                <span class="text-sm text-gray-400">of {{ $user->dataCapGb() }} GB</span>
                            @else
                                <span class="text-sm text-gray-400">of Unlimited</span>
                            @endif
                        </div>
                    </div>
                    @if ($user->data_cap_mb > 0)
                        <div class="flex items-center gap-2 bg-indigo-50 text-indigo-700 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                            <span class="text-sm font-medium">{{ $user->dataUsagePercent() }}%</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-green-50 text-green-700 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                            <span class="text-sm font-medium">Unlimited</span>
                        </div>
                    @endif
                </div>

                @if ($user->data_cap_mb > 0)
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full transition-all duration-500
                            @if($user->dataUsagePercent() >= 90) bg-red-500
                            @elseif($user->dataUsagePercent() >= 70) bg-amber-500
                            @else bg-indigo-500
                            @endif"
                            style="width: {{ $user->dataUsagePercent() }}%">
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ number_format(max(0, $user->data_cap_mb - $user->data_used_mb) / 1024, 2) }} GB remaining
                        &nbsp;·&nbsp; Resets on the 1st of each month
                    </p>
                @else
                    <div class="flex items-center gap-2 text-sm text-gray-500 bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Unlimited data usage &nbsp;·&nbsp; Resets on the 1st of each month</span>
                    </div>
                @endif
            </div>

            {{-- ── VPN Server Info ──────────────────────────────────────────── --}}
            <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </span>
                <span class="text-sm text-gray-600">
                    VPN Server: <code class="font-mono font-semibold text-gray-800 bg-gray-100 px-1.5 py-0.5 rounded">{{ config('services.zenvpn.server_host') }}</code>
                </span>
                <span class="ml-auto flex items-center gap-1.5 text-xs text-green-600 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    Online
                </span>
            </div>

            {{-- ── Devices Section ──────────────────────────────────────────── --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">My Devices</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                    {{-- ── Device Cards ──────────────────────────────────── --}}
                    @foreach ($devices as $device)
                        <div x-data="{
                                panel: null,
                                activeTab: 'vless',
                                copied: false,
                                editName: @js($device->device_name),
                                editSni: @js($device->sni)
                            }"
                            class="relative bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-col gap-4 min-h-[220px]">

                            {{-- ── Default Card View ───────────────────── --}}
                            <div x-show="panel === null">

                                {{-- Card Header --}}
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 shrink-0">
                                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm leading-tight">{{ $device->device_name }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">Added {{ $device->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium shrink-0
                                        {{ $device->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $device->status === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ ucfirst($device->status) }}
                                    </span>
                                </div>

                                {{-- SNI Chip + Last Seen --}}
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 rounded-md text-xs font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                                        </svg>
                                        {{ $device->sniLabel() }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        Last seen: {{ $device->last_seen ? $device->last_seen->diffForHumans() : 'Never' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Usage: <span class="text-gray-900">{{ $device->formattedUsage() }}</span>
                                    </span>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-2 mt-auto">
                                    <button @click="panel = 'config'"
                                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition-colors duration-150">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        Config
                                    </button>

                                    <button @click="panel = 'edit'"
                                        class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors duration-150"
                                        title="Edit device">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                        onsubmit="return confirm('Remove device \'{{ addslashes($device->device_name) }}\'? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-150"
                                            title="Remove device">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- ── Config Panel ─────────────────────────────── --}}
                            <div x-show="panel === 'config'"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute inset-0 bg-white rounded-xl border border-indigo-200 shadow-lg p-5 z-10 flex flex-col gap-3"
                                style="display: none;">

                                <div class="flex items-center justify-between shrink-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate pr-2">{{ $device->device_name }}</p>
                                    <button @click="panel = null" class="text-gray-400 hover:text-gray-600 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Protocol Tabs --}}
                                <div class="flex gap-1 bg-gray-100 rounded-lg p-1 text-xs shrink-0">
                                    <button @click="activeTab = 'vless'"
                                        :class="activeTab === 'vless' ? 'bg-white shadow text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                        class="flex-1 px-2 py-1.5 rounded-md transition-all duration-150">VLESS</button>
                                    <button @click="activeTab = 'trojan'"
                                        :class="activeTab === 'trojan' ? 'bg-white shadow text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                        class="flex-1 px-2 py-1.5 rounded-md transition-all duration-150">Trojan</button>
                                    <button @click="activeTab = 'vmess'"
                                        :class="activeTab === 'vmess' ? 'bg-white shadow text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                                        class="flex-1 px-2 py-1.5 rounded-md transition-all duration-150">VMess</button>
                                </div>

                                {{-- URI Display --}}
                                <div class="relative flex-1 overflow-hidden">
                                    <div x-show="activeTab === 'vless'" class="bg-gray-50 rounded-lg p-3 pr-10 h-full overflow-auto">
                                        <p class="font-mono text-xs text-gray-700 break-all leading-relaxed" id="uri-vless-{{ $device->id }}">{{ $device->getVlessUri() }}</p>
                                    </div>
                                    <div x-show="activeTab === 'trojan'" class="bg-gray-50 rounded-lg p-3 pr-10 h-full overflow-auto" style="display:none">
                                        <p class="font-mono text-xs text-gray-700 break-all leading-relaxed" id="uri-trojan-{{ $device->id }}">{{ $device->getTrojanUri() }}</p>
                                    </div>
                                    <div x-show="activeTab === 'vmess'" class="bg-gray-50 rounded-lg p-3 pr-10 h-full overflow-auto" style="display:none">
                                        <p class="font-mono text-xs text-gray-700 break-all leading-relaxed" id="uri-vmess-{{ $device->id }}">{{ $device->getVmessUri() }}</p>
                                    </div>

                                    {{-- Copy Button --}}
                                    <button
                                        @click="
                                            const id = 'uri-' + activeTab + '-{{ $device->id }}';
                                            const text = document.getElementById(id).innerText;
                                            navigator.clipboard.writeText(text).then(() => { copied = true; setTimeout(() => copied = false, 2000); });
                                        "
                                        class="absolute top-2 right-2 p-1.5 rounded-md text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                        :title="copied ? 'Copied!' : 'Copy URI'">
                                        <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        <svg x-show="copied" class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- SNI note --}}
                                <p class="text-xs text-gray-400 text-center shrink-0">
                                    SNI: <span class="font-medium text-gray-600">{{ $device->sni }}</span>
                                    &nbsp;·&nbsp; Import into v2rayN, Clash, Shadowrocket, etc.
                                </p>
                            </div>

                            {{-- ── Edit Panel ───────────────────────────────── --}}
                            <div x-show="panel === 'edit'"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute inset-0 bg-white rounded-xl border border-amber-200 shadow-lg p-5 z-10 flex flex-col gap-4"
                                style="display: none;">

                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-gray-800">Edit Device</p>
                                    <button @click="panel = null" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('devices.update', $device) }}" class="flex flex-col gap-3 flex-1">
                                    @csrf
                                    @method('PATCH')

                                    {{-- Device Name --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Device Name</label>
                                        <input
                                            type="text"
                                            name="device_name"
                                            x-model="editName"
                                            maxlength="64"
                                            required
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none transition-shadow"
                                        />
                                    </div>

                                    {{-- SNI Dropdown --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">SNI (Server Name)</label>
                                        <select
                                            name="sni"
                                            x-model="editSni"
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none transition-shadow bg-white">
                                            @foreach (\App\Models\VpnDevice::SNI_OPTIONS as $domain => $label)
                                                <option value="{{ $domain }}" {{ $device->sni === $domain ? 'selected' : '' }}>
                                                    {{ $label }} — {{ $domain }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- SNI change warning --}}
                                    <div x-show="editSni !== @js($device->sni)"
                                        class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"
                                        style="display:none">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="text-xs text-amber-700">Changing SNI requires reconnecting on your VPN app with the updated config.</p>
                                    </div>

                                    <button type="submit"
                                        class="mt-auto w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition-colors duration-150">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Save Changes
                                    </button>
                                </form>
                            </div>

                        </div>{{-- /device card --}}
                    @endforeach

                    {{-- ── Add Device Card ──────────────────────────────── --}}
                    @if ($user->canAddDevice())
                        <div x-data="{ showForm: false }" class="bg-white rounded-xl border-2 border-dashed border-gray-200 hover:border-indigo-300 transition-colors duration-200 p-5 flex flex-col min-h-[220px]">

                            {{-- Toggle Button --}}
                            <button @click="showForm = !showForm"
                                x-show="!showForm"
                                class="flex-1 flex flex-col items-center justify-center gap-3 text-gray-400 hover:text-indigo-600 transition-colors duration-150 py-4">
                                <div class="w-12 h-12 rounded-full border-2 border-dashed border-current flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <span class="text-sm font-medium">Add New Device</span>
                                <span class="text-xs text-gray-300">
                                    @if ($user->device_limit === 0)
                                        Unlimited slots
                                    @else
                                        {{ $user->device_limit - $user->vpnDevices->count() }} slot(s) remaining
                                    @endif
                                </span>
                            </button>

                            {{-- Add Device Form --}}
                            <div x-show="showForm"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="flex flex-col gap-3 h-full"
                                style="display: none;">

                                <div class="flex items-center justify-between shrink-0">
                                    <p class="text-sm font-semibold text-gray-800">Add New Device</p>
                                    <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('devices.store') }}" class="flex flex-col gap-3 flex-1">
                                    @csrf

                                    {{-- Device Name --}}
                                    <div>
                                        <label for="device_name" class="block text-xs font-medium text-gray-600 mb-1">Device Name</label>
                                        <input
                                            type="text"
                                            id="device_name"
                                            name="device_name"
                                            value="{{ old('device_name') }}"
                                            placeholder="e.g. My iPhone, Home PC"
                                            maxlength="64"
                                            required
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-shadow"
                                        />
                                    </div>

                                    {{-- SNI Dropdown --}}
                                    <div>
                                        <label for="sni" class="block text-xs font-medium text-gray-600 mb-1">SNI (Server Name)</label>
                                        <select
                                            id="sni"
                                            name="sni"
                                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-shadow bg-white">
                                            @foreach (\App\Models\VpnDevice::SNI_OPTIONS as $domain => $label)
                                                <option value="{{ $domain }}" {{ old('sni', 'm.zoom.us') === $domain ? 'selected' : '' }}>
                                                    {{ $label }} — {{ $domain }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-gray-400">Choose an SNI that works best in your network.</p>
                                    </div>

                                    <button type="submit"
                                        class="mt-auto w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors duration-150">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Provision & Add Device
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        {{-- Limit Reached Card --}}
                        <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 flex flex-col items-center justify-center gap-2 text-center min-h-[220px]">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-600">Device Limit Reached</p>
                            <p class="text-xs text-gray-400">You've used all {{ $user->device_limit }} device slots.</p>
                        </div>
                    @endif

                </div>{{-- /grid --}}
            </div>{{-- /devices section --}}

        </div>
    </div>
</x-app-layout>
