@extends('layouts.app')
@section('title', 'Upload PDF')
@section('page-title', 'Upload PDF Label จาก TikTok Shop')

@section('content')
    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">นำเข้า Label จาก PDF</h3>
            <p class="text-sm text-gray-500 mb-6">
                อัพโหลดไฟล์ PDF ที่ดาวน์โหลดจาก TikTok Shop → ระบบจะอ่านข้อมูลอัตโนมัติ → ตัดสต๊อก FIFO → พร้อมพิมพ์ Label (ซ่อนชื่อสินค้า)
            </p>

            <form action="{{ route('orders.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                {{-- Upload PDF --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ไฟล์ PDF</label>
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center hover:border-blue-400 transition-colors"
                         id="drop-zone">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-gray-500 text-sm mb-2">ลากไฟล์ PDF มาวางที่นี่ หรือ</p>
                        <label class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg text-sm cursor-pointer hover:bg-blue-700">
                            เลือกไฟล์
                            <input type="file" name="pdf_file" accept=".pdf" class="hidden" id="pdf-input">
                        </label>
                        <p class="text-xs text-gray-400 mt-2">รองรับไฟล์ PDF ขนาดไม่เกิน 50MB</p>
                        <p id="file-name" class="text-sm font-medium text-blue-600 mt-3 hidden"></p>
                    </div>
                    <p id="file-error" class="hidden mt-2 text-sm text-red-600 flex items-center gap-1">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        กรุณาเลือกไฟล์ PDF ก่อนนำเข้า
                    </p>
                </div>

                {{-- Submit --}}
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" id="btn-upload"
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg id="btn-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <svg id="btn-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span id="btn-text">นำเข้า PDF</span>
                    </button>
                    <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ยกเลิก</a>
                </div>
            </form>
        </div>

        {{-- วิธีใช้ --}}
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
            <h4 class="font-semibold text-blue-800 mb-3">ขั้นตอนการทำงาน</h4>
            <ol class="space-y-2 text-sm text-blue-700">
                <li class="flex gap-2">
                    <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs flex-shrink-0">1</span>
                    ดาวน์โหลด PDF Label จาก TikTok Shop Seller Center
                </li>
                <li class="flex gap-2">
                    <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs flex-shrink-0">2</span>
                    อัพโหลดไฟล์ PDF — เลือกสินค้าถ้าต้องการตัดสต๊อก (ไม่บังคับ)
                </li>
                <li class="flex gap-2">
                    <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs flex-shrink-0">3</span>
                    ระบบอ่านข้อมูล (Barcode, ผู้รับ, ที่อยู่) + ตัดสต๊อก FIFO (ถ้าเลือกสินค้า)
                </li>
                <li class="flex gap-2">
                    <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs flex-shrink-0">4</span>
                    พิมพ์ Label ใหม่ที่ซ่อนชื่อสินค้า (แสดง "-, -" แทน)
                </li>
            </ol>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    const input = document.getElementById('pdf-input');
    const fileNameEl = document.getElementById('file-name');
    const dropZone = document.getElementById('drop-zone');

    input.addEventListener('change', function() {
        if (this.files[0]) {
            fileNameEl.textContent = this.files[0].name + ' (' + (this.files[0].size / 1024 / 1024).toFixed(1) + ' MB)';
            fileNameEl.classList.remove('hidden');
        }
    });

    ['dragenter', 'dragover'].forEach(e => {
        dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('border-blue-400', 'bg-blue-50'); });
    });
    ['dragleave', 'drop'].forEach(e => {
        dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('border-blue-400', 'bg-blue-50'); });
    });
    dropZone.addEventListener('drop', ev => {
        const file = ev.dataTransfer.files[0];
        if (file && file.type === 'application/pdf') {
            input.files = ev.dataTransfer.files;
            fileNameEl.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
            fileNameEl.classList.remove('hidden');
            document.getElementById('file-error').classList.add('hidden');
        }
    });

    document.querySelector('form').addEventListener('submit', function (e) {
        const errorEl  = document.getElementById('file-error');
        const dropEl   = document.getElementById('drop-zone');

        if (!input.files || input.files.length === 0) {
            e.preventDefault();
            errorEl.classList.remove('hidden');
            dropEl.classList.add('border-red-400', 'bg-red-50');
            dropEl.classList.remove('border-gray-200');
            return;
        }

        // hide error if previously shown
        errorEl.classList.add('hidden');

        // loading state
        const btn     = document.getElementById('btn-upload');
        const icon    = document.getElementById('btn-icon');
        const spinner = document.getElementById('btn-spinner');
        const text    = document.getElementById('btn-text');
        btn.disabled = true;
        icon.classList.add('hidden');
        spinner.classList.remove('hidden');
        text.textContent = 'กำลังอ่านไฟล์...';
    });

    // clear error when file selected
    input.addEventListener('change', function () {
        document.getElementById('file-error').classList.add('hidden');
        const dropEl = document.getElementById('drop-zone');
        dropEl.classList.remove('border-red-400', 'bg-red-50');
        dropEl.classList.add('border-gray-200');
    });
</script>
@endpush
