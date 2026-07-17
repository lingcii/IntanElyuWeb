<?php

namespace App\Http\Controllers;

use App\Models\Merchandise;
use App\Models\MerchReservation;
use App\Models\User;
use Illuminate\Http\Request;

class MerchandiseController extends Controller
{
    // === LUPTO ADMIN ENDPOINTS ===

    public function getAdminInventory(Request $request)
    {
        $items = Merchandise::all();
        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function saveItem(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'price_xp' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $item = isset($request->id) ? Merchandise::findOrFail($request->id) : new Merchandise();
        
        $item->title = $request->title;
        $item->category = $request->category;
        $item->price_xp = $request->price_xp;
        $item->stock = $request->stock;
        
        if ($request->has('badge')) {
            $item->badge = $request->badge;
        }

        // Handle Image Upload if any
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/images/merch'), $filename);
            $item->image = 'assets/images/merch/' . $filename;
        } elseif ($request->has('image') && !empty($request->image)) {
            // Alternatively via URL
            $item->image = $request->image;
        }

        $item->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Item saved successfully',
            'data' => $item
        ]);
    }

    public function deleteItem($id)
    {
        $item = Merchandise::findOrFail($id);
        $item->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Item deleted successfully'
        ]);
    }

    public function getAdminReservations(Request $request)
    {
        $reservations = MerchReservation::with(['user', 'merchandise'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $reservations
        ]);
    }

    public function claimReservation($id)
    {
        $reservation = MerchReservation::findOrFail($id);
        if ($reservation->status === 'claimed') {
            return response()->json(['status' => 'error', 'message' => 'Already claimed']);
        }
        
        $reservation->status = 'claimed';
        $reservation->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Reservation marked as claimed.'
        ]);
    }


    // === MOBILE APP / TOURIST ENDPOINTS ===

    public function index(Request $request)
    {
        // Fetch all merch that has stock > 0
        $items = Merchandise::where('stock', '>', 0)->get();
        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function reserve(Request $request)
    {
        $request->validate([
            'merchandise_id' => 'required|integer'
        ]);

        $user = $request->user();
        $merch = Merchandise::find($request->merchandise_id);

        if (!$merch) {
            return response()->json([
                'status' => 'error',
                'message' => 'Merchandise not found.'
            ], 404);
        }

        if ($merch->stock <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'This item is out of stock.'
            ], 400);
        }

        if ($user->xp < $merch->price_xp) {
            return response()->json([
                'status' => 'error',
                'message' => "Insufficient XP. You need {$merch->price_xp} XP but only have {$user->xp} XP."
            ], 400);
        }

        // Proceed to reserve
        $user->xp -= $merch->price_xp;
        $user->save();

        $merch->stock -= 1;
        $merch->save();

        $reservation = MerchReservation::create([
            'user_id' => $user->id,
            'merchandise_id' => $merch->id,
            'status' => 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Reservation successful.',
            'data' => $reservation,
            'new_xp' => $user->xp
        ]);
    }
}
