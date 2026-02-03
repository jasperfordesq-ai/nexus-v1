/**
 * Listing Detail Page - View a single listing
 *
 * Features:
 * - Fetch listing by ID from URL param
 * - Show title, type badge, description, category
 * - Show owner info with avatar
 * - CTA button to message owner
 * - Loading, error, and not found states
 */

import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  Chip,
  Spinner,
  Avatar,
  Button,
  Divider,
} from '@heroui/react';
import { getListingById, type ListingDetail, ApiClientError } from '../api';
import { useTenant } from '../tenant';
import { useAuth } from '../auth';

// ===========================================
// HELPERS
// ===========================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

// Icons
function ArrowLeftIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
  );
}

function MessageIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
  );
}

function LocationIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  );
}

function HeartIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
    </svg>
  );
}

function ChatBubbleIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
    </svg>
  );
}

// ===========================================
// LISTING DETAIL PAGE
// ===========================================

export function ListingDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const tenant = useTenant();
  const { isAuthenticated, user } = useAuth();

  const [listing, setListing] = useState<ListingDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notFound, setNotFound] = useState(false);

  const timeUnit = tenant.config?.time_unit || 'hour';
  const timeUnitPlural = tenant.config?.time_unit_plural || 'hours';

  useEffect(() => {
    async function fetchListing() {
      if (!id) {
        setNotFound(true);
        setLoading(false);
        return;
      }

      const listingId = parseInt(id, 10);
      if (isNaN(listingId)) {
        setNotFound(true);
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);
      setNotFound(false);

      try {
        const response = await getListingById(listingId);
        setListing(response.data);
      } catch (err) {
        if (err instanceof ApiClientError) {
          if (err.status === 404) {
            setNotFound(true);
          } else {
            setError(err.message);
          }
        } else {
          setError('Failed to load listing');
        }
      } finally {
        setLoading(false);
      }
    }

    fetchListing();
  }, [id]);

  // Handle message owner
  const handleMessageOwner = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: `/listings/${id}` } });
      return;
    }
    // Navigate to messages with the listing owner
    // For now, just go to messages page (placeholder)
    navigate('/messages');
  };

  // Loading state
  if (loading) {
    return (
      <div className="flex justify-center py-12">
        <Spinner size="lg" />
      </div>
    );
  }

  // Not found state
  if (notFound) {
    return (
      <div className="text-center py-12">
        <svg
          className="w-20 h-20 mx-auto text-gray-300 mb-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth={1}
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h2 className="text-xl font-semibold mb-2">Listing Not Found</h2>
        <p className="text-gray-500 mb-4">
          This listing may have been removed or doesn't exist.
        </p>
        <Button as={Link} to="/listings" variant="flat" color="primary">
          Browse Listings
        </Button>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="text-center py-12">
        <div className="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded mb-4 inline-block">
          {error}
        </div>
        <div>
          <Button
            variant="flat"
            onPress={() => window.location.reload()}
            className="mr-2"
          >
            Try Again
          </Button>
          <Button as={Link} to="/listings" variant="light">
            Back to Listings
          </Button>
        </div>
      </div>
    );
  }

  if (!listing) {
    return null;
  }

  // Get author info
  const authorName = listing.author_name || 'Unknown';
  const authorAvatar = listing.author_avatar;
  const isOwnListing = user && listing.user_id === user.id;

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Back button */}
      <Button
        as={Link}
        to="/listings"
        variant="light"
        startContent={<ArrowLeftIcon className="w-4 h-4" />}
        className="mb-4"
      >
        Back to Listings
      </Button>

      {/* Main content card */}
      <Card>
        <CardBody className="p-6">
          {/* Header */}
          <div className="flex flex-col sm:flex-row gap-4 mb-6">
            {/* Image */}
            {listing.image_url && (
              <img
                src={listing.image_url}
                alt={listing.title}
                className="w-full sm:w-48 h-48 object-cover rounded-lg"
              />
            )}

            {/* Title and meta */}
            <div className="flex-1">
              <div className="flex flex-wrap items-start gap-2 mb-2">
                <h1 className="text-2xl font-bold">{listing.title}</h1>
                <Chip
                  size="sm"
                  color={listing.type === 'offer' ? 'success' : 'warning'}
                  variant="flat"
                >
                  {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                </Chip>
              </div>

              {/* Category */}
              {listing.category_name && (
                <Chip
                  size="sm"
                  variant="flat"
                  className="mb-3"
                  style={listing.category_color ? { backgroundColor: `${listing.category_color}20`, color: listing.category_color } : undefined}
                >
                  {listing.category_name}
                </Chip>
              )}

              {/* Time credits */}
              {listing.time_credits !== undefined && listing.time_credits > 0 && (
                <p className="text-lg font-semibold text-primary mb-2">
                  {listing.time_credits} {listing.time_credits === 1 ? timeUnit : timeUnitPlural}
                </p>
              )}

              {/* Location */}
              {listing.location && (
                <div className="flex items-center gap-2 text-gray-500 text-sm mb-2">
                  <LocationIcon className="w-4 h-4" />
                  <span>{listing.location}</span>
                </div>
              )}

              {/* Stats */}
              <div className="flex items-center gap-4 text-sm text-gray-500">
                {listing.likes_count !== undefined && (
                  <div className="flex items-center gap-1">
                    <HeartIcon className="w-4 h-4" />
                    <span>{listing.likes_count}</span>
                  </div>
                )}
                {listing.comments_count !== undefined && (
                  <div className="flex items-center gap-1">
                    <ChatBubbleIcon className="w-4 h-4" />
                    <span>{listing.comments_count}</span>
                  </div>
                )}
                <span>Posted {formatDate(listing.created_at)}</span>
              </div>
            </div>
          </div>

          <Divider className="my-6" />

          {/* Description */}
          <div className="mb-6">
            <h2 className="text-lg font-semibold mb-3">Description</h2>
            <div className="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap">
              {listing.description || 'No description provided.'}
            </div>
          </div>

          {/* Attributes */}
          {listing.attributes && listing.attributes.length > 0 && (
            <>
              <Divider className="my-6" />
              <div className="mb-6">
                <h2 className="text-lg font-semibold mb-3">Details</h2>
                <div className="flex flex-wrap gap-2">
                  {listing.attributes.map((attr) => (
                    <Chip key={attr.id} size="sm" variant="bordered">
                      {attr.name}: {attr.value}
                    </Chip>
                  ))}
                </div>
              </div>
            </>
          )}

          <Divider className="my-6" />

          {/* Owner section */}
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <Avatar
                src={authorAvatar || undefined}
                name={authorName}
                size="lg"
              />
              <div>
                <p className="font-semibold">{authorName}</p>
                <p className="text-sm text-gray-500">
                  Listed on {formatDateTime(listing.created_at)}
                </p>
              </div>
            </div>

            {!isOwnListing && (
              <Button
                color="primary"
                startContent={<MessageIcon className="w-4 h-4" />}
                onPress={handleMessageOwner}
              >
                {isAuthenticated ? 'Message Owner' : 'Sign In to Message'}
              </Button>
            )}

            {isOwnListing && (
              <Chip color="default" variant="flat">
                This is your listing
              </Chip>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Updated timestamp */}
      {listing.updated_at && listing.updated_at !== listing.created_at && (
        <p className="text-sm text-gray-400 text-center">
          Last updated {formatDateTime(listing.updated_at)}
        </p>
      )}
    </div>
  );
}
