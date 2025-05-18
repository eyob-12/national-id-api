<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser;

class IDCardController extends Controller
{
    public function extractImagesFromPDF(Request $request)
{

    $publicImageDir = public_path('images/temp');
    if (!file_exists($publicImageDir)) {
        mkdir($publicImageDir, 0755, true);
    }

    $request->validate([
        'id_pdf' => 'required|mimes:pdf|max:2048',
    ]);

    $pdfFile = $request->file('id_pdf');
    $filename = uniqid('pdf_') . '.pdf';
    $pdfPath = storage_path("app/public/temp/{$filename}");
    $pdfFile->move(storage_path('app/public/temp'), $filename);

    // Convert PDF to image (first page)
    $imgId = Str::random(8);
    $imgBase = public_path('images/temp/' . $imgId);
    $process = new Process(['pdftoppm', '-jpeg', '-singlefile', $pdfPath, $imgBase]);
    $process->run();

    if (!$process->isSuccessful()) {
        \Log::error('pdftoppm failed', [
            'command' => $process->getCommandLine(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ]);
    }

    $imagePath = $imgBase . '.jpg';
    if (!file_exists($imagePath)) {
        return response()->json(['error' => 'Failed to render PDF to image.'], 500);
    }

    $img = Image::make($imagePath);

    // === CROP USER IMAGE ===
    $userRawPath = $imgBase . '_user_raw.jpg';
    $userCleanPath = $imgBase . '_user.png'; // transparent PNG output

    $img = Image::make($imagePath);
    $img->crop(172, 205, 115, 211)->save($userRawPath);

    $rembgPath = '/home/eyob12/rembg-venv/bin/rembg';
    $rembgProcess = new Process(
        [$rembgPath, 'i', $userRawPath, $userCleanPath],
        null, // cwd
        ['PATH' => getenv('PATH')] // inject current system PATH
    );
    $rembgProcess->run();


    if (!$rembgProcess->isSuccessful() || !file_exists($userCleanPath)) {
        return response()->json(['error' => 'Background removal failed.'], 500);
    }

    // === CROP ISSUE DATE (GC) ===
    $issueGCRaw = $imgBase . '_issue_date_gc_raw.jpg';
    $issueGCClean = $imgBase . '_issue_date_gc.png';
    
    $img = Image::make($imagePath);
    $img->crop(17, 86, 1120, 269)->save($issueGCRaw);
    
    // Remove white background using ImageMagick
    (new Process([
        'convert',
        $issueGCRaw,
        '-fuzz', '10%',
        '-transparent', 'white',
        $issueGCClean
    ], null, ['PATH' => getenv('PATH')]))->run();
    
    // === CROP ISSUE DATE (EC) ===
    $issueECRaw = $imgBase . '_issue_date_ec_raw.jpg';
    $issueECClean = $imgBase . '_issue_date_ec.png';
    
    $img = Image::make($imagePath);
    $img->crop(17, 77, 1120, 362)->save($issueECRaw);
    
    (new Process([
        'convert',
        $issueECRaw,
        '-fuzz', '10%',
        '-transparent', 'white',
        $issueECClean
    ], null, ['PATH' => getenv('PATH')]))->run();
    
    // === CROP EXPIRE DATE ===
    $expireRaw = $imgBase . '_expire_date_raw.jpg';
    $expireClean = $imgBase . '_expire_date.png';
    
    $img = Image::make($imagePath);
    $img->crop(145, 16, 866, 576)->save($expireRaw);
    
    (new Process([
        'convert',
        $expireRaw,
        '-fuzz', '10%',
        '-transparent', 'white',
        $expireClean
    ], null, ['PATH' => getenv('PATH')]))->run();
    
    $fanCropPath = $imgBase . '_fan.jpg';
    $img = Image::make($imagePath);
    $img->crop(143, 49, 913, 602)->save($fanCropPath);

    $finCropPath = $imgBase . '_fin.jpg';
    $img = Image::make($imagePath);
    $img->crop(99, 24, 1033, 1025)->save($finCropPath);

    // === CROP QR IMAGE ===
    $qrCropPath = $imgBase . '_qr.jpg';
    $img = Image::make($imagePath); // reload original
    $img->crop(340, 337, 230, 850)->save($qrCropPath); // adjust (x, y) as needed

    return response()->json([
        'status' => 'success',
        'user_image_url' => asset('image-proxy/' . basename($userCleanPath)),
        'qr_code_url'    => asset('image-proxy/' . basename($qrCropPath)),
        'issue_date_gc'    => asset('image-proxy/' . basename($issueGCClean)),
        'issue_date_ec'    => asset('image-proxy/' . basename($issueECClean)),
        'expire_date'    => asset('image-proxy/' . basename($expireClean)),
        'fan'           => asset('image-proxy/' . basename($fanCropPath)),
        'fin'           => asset('image-proxy/' . basename($finCropPath)),
    ]);
}


public function extractTextFromPDF(Request $request)
{
    $request->validate([
        'pdf' => 'required|file|mimes:pdf',
    ]);

    $pdf = $request->file('pdf');
    $filename = uniqid('pdf_') . '.pdf';
    $pdfPath = storage_path("app/temp/{$filename}");
    $pdf->move(storage_path('app/temp'), $filename);

    $textPath = str_replace('.pdf', '.txt', $pdfPath);
    exec("pdftotext -layout {$pdfPath} {$textPath}");

    if (!file_exists($textPath)) {
        return response()->json(['error' => 'Failed to extract text.'], 500);
    }

    $lines = file($textPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_map('trim', $lines);

    $amharicName = $lines[6] ?? null; // line 15 (index 14)
    // Helper to get first word before spacing
    $firstWord = fn($line) => preg_split('/\s+/', trim($line))[0] ?? null;
    // Extract only the English name from line 16
    $line16 = $lines[7] ?? '';
    $englishName = null;
    
    // Use regex to remove any leading "FCN:", digits, and whitespace
    $englishName = preg_replace('/^(FCN:\s*)?\d+(?:\s+\d+)*\s*/', '', $line16);
    $englishName = trim($englishName);

       // Date of Birth
    $dobGCLine = $lines[11] ?? ''; // line 21
    $dobECLine = $lines[10] ?? ''; // line 22

    $dobGC = preg_split('/\s+/', trim($dobGCLine))[0] ?? null;
    $dobEC = preg_split('/\s+/', trim($dobECLine))[0] ?? null;

        // Sex
    $sexAmharic = $firstWord($lines[13] ?? ''); // line 14
    $sexEnglish = $firstWord($lines[14] ?? ''); // line 15

    // Nationality
    $nationalityAm = $firstWord($lines[16] ?? ''); // line 17
    $nationalityEn = $firstWord($lines[17] ?? ''); // line 18

    // Phone
    $phone = trim($lines[19] ?? ''); // line 19

    // Region
    $regionAm = preg_split('/\s{2,}/', $lines[10] ?? '')[1] ?? ''; // line 22
    $regionEn = preg_split('/\s{2,}/', $lines[11] ?? '')[1] ?? '';

    // Subcity
    $subcityAm = preg_split('/\s{2,}/', $lines[13] ?? '')[1] ?? ''; // line 14
    $subcityEn = preg_split('/\s{2,}/', $lines[14] ?? '')[1] ?? ''; // line 15

    // Woreda
    $woredaAm = preg_split('/\s{2,}/', $lines[16] ?? '')[1] ?? ''; // line 17
    $woredaEn = preg_split('/\s{2,}/', $lines[17] ?? '')[1] ?? ''; // line 18

    // Cleanup
    unlink($pdfPath);
    unlink($textPath);

    return response()->json([
        'status' => 'success',
        'data' => [
        'full_name'     => [ $amharicName, $englishName ],
        'date_of_birth' => [$dobEC, $dobGC],
        'sex'            => [$sexAmharic, $sexEnglish],
        'nationality'    => [$nationalityAm, $nationalityEn],
        'phone'          => $phone,
        'region'         => [$regionAm, $regionEn],
        'subcity'        => [$subcityAm, $subcityEn],
        'woreda'         => [$woredaAm, $woredaEn],
        ]
    ]);
}



}
